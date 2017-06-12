<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * AbstractGigyaAccountEnricher
 *
 * This enricher takes in charge the enrichment of Gigya account data with Magento customer entity data.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 * When it's triggered it will :
 * . check that the Gigya data have to be enriched
 * . trigger the event AbstractGigyaAccountEnricher::EVENT_MAP_GIGYA_FROM_MAGENTO so that the Gigya data could be enriched with third party code and with the extended fields mapping
 */
class AbstractGigyaAccountEnricher extends AbstractEnricher implements ObserverInterface
{
    /**
     * This event is dispatched before the enrichment is done
     */
    const EVENT_MAP_GIGYA_FROM_MAGENTO = 'gigya_map_from_magento';

    const EVENT_MAP_GIGYA_FROM_MAGENTO_SUCCESS = 'gigya_success_map_from_magento';

    const EVENT_MAP_GIGYA_FROM_MAGENTO_FAILURE = 'gigya_failed_map_from_magento';

    /** @var  GigyaSyncHelper */
    protected $gigyaSyncHelper;

    /** @var  GigyaAccountRepositoryInterface */
    protected $gigyaAccountRepository;

    /** @var ManagerInterface */
    protected $eventDispatcher;

    /** @var  GigyaLogger */
    protected $logger;

    /**
     * AbstractGigyaAccountEnricher constructor.
     *
     * @param GigyaAccountRepositoryInterface $gigyaAccountRepository
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param ManagerInterface $eventDispatcher
     * @param GigyaLogger $logger
     */
    public function __construct(
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        ManagerInterface $eventDispatcher,
        GigyaLogger $logger
    )
    {
        $this->gigyaAccountRepository = $gigyaAccountRepository;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Check if a Magento customer entity's data are to used to enrich the data to the Gigya service.
     *
     * Will return true if the customer is not null, not flagged as deleted, not a new customer, not flagged has already synchronized, has a non empty gigya_uid value,
     * and if this customer id is not explicitly flagged has not to be synchronized (@see GigyaSyncHelper::isProductIdExcludedFromSync())
     *
     * @param Customer $magentoCustomer
     * @return bool
     */
    protected function shallEnrichGigyaWithMagentoCustomerData($magentoCustomer)
    {
        $result =
            $magentoCustomer != null
            && !$magentoCustomer->isDeleted()
            && !$magentoCustomer->isObjectNew()
            && !$this->retrieveRegisteredCustomer($magentoCustomer)
            && !(empty($magentoCustomer->getGigyaUid()))
            && !$this->gigyaSyncHelper->isCustomerIdExcludedFromSync(
                $magentoCustomer->getId(), GigyaSyncHelper::DIR_CMS2G
            );

        return $result;
    }

    /**
     * @param $magentoCustomer Customer
     * @return bool
     */
    protected function shallUpdateGigya($magentoCustomer)
    {
        $result = $magentoCustomer->getData('update_gigya') === true;

        return $result;
    }

    /**
     * Method called if an exception is caught when dispatching event AbstractGigyaAccountEnricher::EVENT_MAP_GIGYA_FROM_MAGENTO
     *
     * Default behavior is to log a warning (exception is muted)
     *
     * @param $e \Exception
     * @param $magentoCustomer Customer
     * @param $gigyaAccountData GigyaUser
     * @param $gigyaAccountLoggingEmail string
     * @return boolean Whether the enrichment can go on or not. Default is true.
     */
    protected function processEventMapGigyaFromMagentoException($e, $magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail)
    {
        $this->logger->warning(
            'Exception raised when enriching Gigya account with Magento data.',
            [
                'exception' => $e,
                'customer_entity_id' => ($magentoCustomer != null) ? $magentoCustomer->getEntityId() : 'customer is null',
                'gigya_uid' => ($gigyaAccountData != null) ? $gigyaAccountData->getUID() : 'Gigya data are null',
                'gigya_logging_email' => $gigyaAccountLoggingEmail
            ]
        );

        return true;
    }

    /**
     * Performs the enrichment of the Gigya account with the Magento data.
     *
     * @param $magentoCustomer Customer
     * @return GigyaUser
     * @throws \Exception
     */
    protected function enrichGigyaAccount($magentoCustomer)
    {
        $this->pushRegisteredCustomer($magentoCustomer);

        $gigyaAccountData = $this->gigyaAccountRepository->get($magentoCustomer->getGigyaUid());
        $gigyaAccountLoggingEmail = $this->gigyaSyncHelper->getMagentoCustomerAndLoggingEmail($gigyaAccountData)['logging_email'];

        try {
            $this->eventDispatcher->dispatch(self::EVENT_MAP_GIGYA_FROM_MAGENTO, [
                "gigya_user" => $gigyaAccountData,
                "customer" => $magentoCustomer->getDataModel()
            ]);
            $this->eventDispatcher->dispatch(self::EVENT_MAP_GIGYA_FROM_MAGENTO_SUCCESS, [
                "gigya_uid" => $gigyaAccountData->getUID(),
                "customer_entity_id" => $magentoCustomer->getEntityId()
            ]);
        } catch (\Exception $e) {
            $this->eventDispatcher->dispatch(self::EVENT_MAP_GIGYA_FROM_MAGENTO_FAILURE, [
                "gigya_uid" => $gigyaAccountData->getUID(),
                "customer_entity_id" => $magentoCustomer->getEntityId()
            ]);
            if (!$this->processEventMapGigyaFromMagentoException($e, $magentoCustomer, $gigyaAccountData,
                $gigyaAccountLoggingEmail)
            ) {
                throw new GigyaFieldMappingException($e);
            }
        }

        $gigyaAccountData->setCustomerEntityId($magentoCustomer->getEntityId());

        return $gigyaAccountData;
    }

    /**
     * Will synchronize Gigya account with Magento account entity if needed.
     *
     * @param Observer $observer Must hang a data 'customer' of type Magento\Customer\Model\Customer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Customer $customer */
        $magentoCustomer = $observer->getData('customer');
        /** @var GigyaUser $gigyaAccountData */
        $gigyaAccountData = null;

        if ($this->shallEnrichGigyaWithMagentoCustomerData($magentoCustomer)) {
            $gigyaAccountData = $this->enrichGigyaAccount($magentoCustomer);
            $magentoCustomer->setData('update_gigya', true);
        }

        if ($this->shallUpdateGigya($magentoCustomer)) {
            if ($gigyaAccountData == null) {
                $gigyaAccountData = $this->gigyaAccountRepository->get($magentoCustomer->getGigyaUid());
            }
            $this->gigyaAccountRepository->update($gigyaAccountData);
        }
    }
}
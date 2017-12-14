<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\CmsStarterKit\user\GigyaProfile;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Model\FieldMapping\GigyaFromMagento;
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
 * . enrich the Gigya data with extended fields mapping
 */
class AbstractGigyaAccountEnricher extends AbstractEnricher implements ObserverInterface
{
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
     * @var GigyaFromMagento
     */
    protected $gigyaFromMagento;

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
        GigyaLogger $logger,
        GigyaFromMagento $gigyaFromMagento
    )
    {
        $this->gigyaAccountRepository = $gigyaAccountRepository;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->gigyaFromMagento = $gigyaFromMagento;
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
     * Method called if an exception is caught when mapping Magento data to Gigya
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

        $gigyaAccountData = new GigyaUser(null);
        $gigyaAccountData->setUID($magentoCustomer->getGigyaUid());
        $gigyaAccountData->setProfile(new GigyaProfile(null));
        $gigyaAccountLoggingEmail = $magentoCustomer->getEmail();

        try {
            $this->gigyaFromMagento->run($magentoCustomer->getDataModel(), $gigyaAccountData);

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
        $gigyaAccountData->setCustomerEntityEmail($magentoCustomer->getEmail());

        $customerDatas = $this->gigyaFromMagento->getMagentoUserObserver();
        $fields =  file_get_contents($this->gigyaFromMagento->getFieldsMappingFile());
        if($fields && !empty($fields)){
            $dataFiles = json_decode($fields,true);
            $dataArray = [];
            foreach($dataFiles as $dataFile){
                if(isset($dataFile['cmsName']) && isset($dataFile['direction']) && isset($dataFile['gigyaName'])) {
                    if (array_key_exists(preg_replace('/custom_/','',$dataFile['cmsName']),$customerDatas)){
                        if($dataFile['direction'] == 'both' || $dataFile['direction'] == 'cms2g' ){
                            $dataArray[preg_replace('/data./','',$dataFile['gigyaName'])]=$customerDatas[preg_replace('/custom_/','',$dataFile['cmsName'])]->getValue();
                        }
                    }
                }
            }
            $gigyaAccountData->setData($dataArray);
        }



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
            $this->gigyaAccountRepository->update($gigyaAccountData);
        }
    }
}
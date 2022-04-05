<?php

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaProfile;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Model\FieldMapping\GigyaFromMagento;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResourceModel;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Address\AbstractAddress;
use Magento\Customer\Model\Address;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Gigya\GigyaIM\Model\Config as GigyaConfig;

/**
 * AbstractGigyaAccountEnricher
 *
 * This enricher takes in charge the enrichment of Gigya account data with Magento customer entity data.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 * When it's triggered it will :
 * . check that the Gigya data have to be enriched
 * . enrich the Gigya data with extended field mapping
 */
class AbstractGigyaAccountEnricher implements ObserverInterface
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

    /** @var GigyaFromMagento */
    protected $gigyaFromMagento;

    /** @var GigyaConfig */
	protected $config;

    /** @var EnricherCustomerRegistry */
    protected $enricherCustomerRegistry;

    /** @var CustomerResourceModel */
    protected $customerResourceModel;

    /** @var CustomerFactory */
    protected $customerFactory;

    /**
     * AbstractGigyaAccountEnricher constructor.
     *
     * @param GigyaAccountRepositoryInterface $gigyaAccountRepository
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param ManagerInterface $eventDispatcher
     * @param GigyaLogger $logger
     * @param GigyaFromMagento $gigyaFromMagento
     * @param GigyaConfig $config
     * @param EnricherCustomerRegistry $enricherCustomerRegistry
     * @param CustomerResourceModel $customerResourceModel
     * @param CustomerFactory $customerFactory
     */
    public function __construct(
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        ManagerInterface $eventDispatcher,
        GigyaLogger $logger,
        GigyaFromMagento $gigyaFromMagento,
		GigyaConfig $config,
        EnricherCustomerRegistry $enricherCustomerRegistry,
        CustomerResourceModel $customerResourceModel,
        CustomerFactory $customerFactory
    ) {
        $this->gigyaAccountRepository = $gigyaAccountRepository;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->gigyaFromMagento = $gigyaFromMagento;
        $this->config = $config;
        $this->enricherCustomerRegistry = $enricherCustomerRegistry;
        $this->customerResourceModel = $customerResourceModel;
        $this->customerFactory = $customerFactory;
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
    protected function shallEnrichGigyaWithMagentoCustomerData($magentoCustomer, $final = true)
    {
        $this->logger->debug("Shall enrich Gigya with Magento customer data?");

        if ($magentoCustomer === null) {
            $this->logger->debug("No, no customer found");
        } elseif ($magentoCustomer->isDeleted() === true) {
            $this->logger->debug("No, customer is deleted");
        } elseif ($magentoCustomer->isObjectNew() === true) {
            $this->logger->debug("No, customer is new");
        } elseif (is_object($this->enricherCustomerRegistry->retrieveRegisteredCustomer($magentoCustomer)) === true) {
            $this->logger->debug("No, customer is already in enrichment process");
        } elseif (empty($magentoCustomer->getGigyaUid()) === true) {
            $this->logger->debug("No, customer does not have a Gigya ID");
        } elseif ($this->gigyaSyncHelper->isCustomerIdExcludedFromSync(
            $magentoCustomer->getId(),
            GigyaSyncHelper::DIR_CMS2G
        )) {
            $this->logger->debug("No, customer it is excluded from sync");
        } else {
            if ($final === true) {
                $this->logger->debug("Yes, enrich Gigya with Magento customer data");
            }
            return true;
        }

        return false;
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
     * @param Customer $magentoCustomer
	 *
     * @return GigyaUser
	 *
     * @throws \Exception
     */
    protected function enrichGigyaAccount($magentoCustomer)
    {
        $this->logger->debug("Enriching Gigya account with Magento data");

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

        return $gigyaAccountData;
    }

    /**
     * Will synchronize Gigya account with Magento account entity if needed.
     *
     * @param Observer $observer Must hang a data 'customer' of type Magento\Customer\Model\Customer
     * @return void
	 *
	 * @throws \Exception
	 * @throws GSApiException
     */
    public function execute(Observer $observer)
    {
    	if ($this->config->isGigyaEnabled()) {
            $this->logger->debug("Update customer Magento => Gigya on event " . $observer->getEvent()->getName());

            $dataObject = $observer->getData('data_object');

            /** @var Customer $magentoCustomer */
            if ($dataObject instanceof AbstractAddress) {
                /** @var Address $dataObject */
                $magentoCustomer = $dataObject->getCustomer();
            } else {
                /** @var Customer $dataObject */
                $magentoCustomer = $dataObject;
            }

			if ($this->shallEnrichGigyaWithMagentoCustomerData($magentoCustomer)) {
                $this->enricherCustomerRegistry->pushRegisteredCustomer($magentoCustomer);
                $magentoCustomerId = $magentoCustomer->getId();
                $magentoCustomer = $this->customerFactory->create();
                $this->customerResourceModel->load($magentoCustomer, $magentoCustomerId);

                // Event was triggered by an address save
                if ($dataObject instanceof AbstractAddress) {
                    $addressId = (int) $dataObject->getId();
                    $billingAddressId = (int) $magentoCustomer->getDefaultBillingAddress()->getId();

                    if ($addressId != $billingAddressId) {
                        $this->logger->debug('Will not update as the address it is not the billing one');
                    }
                }

                /** @var GigyaUser $gigyaAccountData */
				$gigyaAccountData = $this->enrichGigyaAccount($magentoCustomer);
				$this->gigyaAccountRepository->update($gigyaAccountData);

                $this->enricherCustomerRegistry->removeRegisteredCustomer($magentoCustomer);
			}
		}
    }
}

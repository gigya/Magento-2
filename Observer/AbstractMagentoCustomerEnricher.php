<?php

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException;
use Gigya\PHP\GSException;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Model\FieldMapping\GigyaToMagento;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\ManagerInterface;
use \Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * AbstractMagentoCustomerEnricher
 *
 * Will enrich a Magento customer entity's fields with the Gigya account data.
 *
 * When it's triggered it will :
 * . check that the Magento data have to be enriched
 * . enrich the Magento required fields with the Gigya attributes (first name, last name, email)
 *
 */
abstract class AbstractMagentoCustomerEnricher implements ObserverInterface
{
    const EVENT_MAP_GIGYA_TO_MAGENTO_SUCCESS = 'gigya_success_map_to_magento';

    const EVENT_MAP_GIGYA_TO_MAGENTO_FAILURE = 'gigya_failed_map_to_magento';

    /** @var  CustomerRepositoryInterface */
    protected $customerRepository;

    /** @var  GigyaAccountRepositoryInterface */
    protected $gigyaAccountRepository;

    /** @var  GigyaSyncHelper */
    protected $gigyaSyncHelper;

    /** @var ManagerInterface */
    protected $eventDispatcher;

    /** @var  GigyaLogger */
    protected $logger;

    /** @var GigyaToMagento */
    protected $gigyaToMagentoMapper;

    /**
     * Registry used to avoid conflicts between enrichers
     * @var EnricherCustomerRegistry
     */
    protected $enricherCustomerRegistry;

    /**
     * Array used to avoid enriching the same customer twice
     * @var int[]
     */
    protected $enrichedCustomers = [];

    /**
     * AbstractMagentoCustomerEnricher constructor.
     *
     * @param CustomerRepositoryInterface $customerRepository
     * @param GigyaAccountRepositoryInterface $gigyaAccountRepository
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param ManagerInterface $eventDispatcher
     * @param GigyaLogger $logger
     * @param GigyaToMagento $gigyaToMagentoMapper
     * @param EnricherCustomerRegistry $enricherCustomerRegistry
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        ManagerInterface $eventDispatcher,
        GigyaLogger $logger,
        GigyaToMagento $gigyaToMagentoMapper,
        EnricherCustomerRegistry $enricherCustomerRegistry
    ) {
        $this->customerRepository = $customerRepository;
        $this->gigyaAccountRepository = $gigyaAccountRepository;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->gigyaToMagentoMapper = $gigyaToMagentoMapper;
        $this->enricherCustomerRegistry = $enricherCustomerRegistry;
    }

    /**
     * Check if Magento customer entity must be enriched with the Gigya's account data.
     *
     * Will return true if the customer is not null, not flagged as deleted, not a new customer, not flagged has already synchronized, has a non empty gigya_uid value,
     * and if this customer id is not explicitly flagged has not to be synchronized (@see GigyaSyncHelper::isCustomerIdExcludedFromSync())
     *
     * @param Customer $magentoCustomer
     * @return bool
     */
    protected function shallEnrichMagentoCustomerWithGigyaAccount($magentoCustomer, $event, $final = true)
    {
        $this->logger->debug("Shall enrich Magento customer with Gigya data?");

        $key = $this->enricherCustomerRegistry->getCustomerRegistryKey($magentoCustomer);

        if ($magentoCustomer === null) {
            $this->logger->debug("No, no customer found");
        } elseif ($magentoCustomer->isDeleted() === true) {
            $this->logger->debug("No, customer is deleted");
        } elseif ($magentoCustomer->isObjectNew() === true) {
            $this->logger->debug("No, customer is new");
        } elseif (isset($this->enrichedCustomers[$key])) {
            $this->logger->debug("No, customer is already enriched");
        } elseif (is_object($this->enricherCustomerRegistry->retrieveRegisteredCustomer($magentoCustomer)) === true) {
            $this->logger->debug("No, customer is already in enrichment process");
        } elseif (empty($magentoCustomer->getGigyaUid()) === true) {
            $this->logger->debug("No, customer does not have a Gigya ID");
        } elseif ($this->gigyaSyncHelper->isCustomerIdExcludedFromSync(
            $magentoCustomer->getId(),
            GigyaSyncHelper::DIR_G2CMS
        )) {
            $this->logger->debug("No, customer it is excluded from sync");
        } else {
            if ($final === true) {
                $this->logger->debug("Yes, enrich Magento customer with Gigya data");
            }
            return true;
        }

        return false;
    }

    /**
     * Method called if an exception is caught when mapping Gigya fields to Magento
     *
     * Default behavior is to log a warning (exception is muted)
     *
     * @param $e \Exception
     * @param $magentoCustomer Customer
     * @param $gigyaAccountData GigyaUser
     * @param $gigyaAccountLoggingEmail string
     * @return boolean Whether the enrichment can go on or not. Default is true.
     */
    protected function processEventMapGigyaToMagentoException($e, $magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail) {

        // Ignore : enrichment shall not fail on third party code exception
        $this->logger->warning(
            'Exception raised when enriching Magento customer with Gigya data.',
            [
                'exception' => $e,
                'customer_entity_id' => ($magentoCustomer != null) ? $magentoCustomer->getId() : 'customer is null',
                'gigya_uid' => ($gigyaAccountData != null) ? $gigyaAccountData->getUID() : 'Gigya data are null',
                'gigya_logging_email' => $gigyaAccountLoggingEmail
            ]
        );

        return true;
    }

	/**
	 * Given a Magento customer, retrieves the corresponding Gigya account data from the Gigya service.
	 *
	 * @param $magentoCustomer
	 *
	 * @return array [
	 *                  'gigya_user' => GigyaUser : the data from the Gigya service
	 *                  'gigya_logging_email' => string : the email for logging as set on this Gigya account
	 *               ]
	 *
	 * @throws GSException
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
    protected function getGigyaDataForEnrichment($magentoCustomer)
    {
        $gigyaAccountData = $this->gigyaAccountRepository->get($magentoCustomer->getGigyaUid());
        $gigyaAccountLoggingEmail = $this->gigyaSyncHelper->getMagentoCustomerAndLoggingEmail($gigyaAccountData)['logging_email'];

        return [
            'gigya_user' => $gigyaAccountData,
            'gigya_logging_email' => $gigyaAccountLoggingEmail
        ];
    }

    /**
     * Performs the enrichment of the customer with the Gigya data
     *
     * @param $magentoCustomer Customer
     * @param $gigyaAccountData GigyaUser
     * @param $gigyaAccountLoggingEmail string
	 *
     * @return Customer The updated Magento customer entity.
	 *
     * @throws \Exception
     */
    protected function enrichMagentoCustomerWithGigyaData($magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail)
    {
		if (is_null($gigyaAccountData)) {
			return $magentoCustomer;
		}

        $this->logger->debug("Enriching Magento customer with Gigya data");

        $this->gigyaSyncHelper->updateMagentoCustomerRequiredFieldsWithGigyaData($magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail);

        try {
            $this->gigyaToMagentoMapper->run($magentoCustomer, $gigyaAccountData);

            $this->eventDispatcher->dispatch(self::EVENT_MAP_GIGYA_TO_MAGENTO_SUCCESS, [
                "gigya_uid" => $gigyaAccountData->getUID(),
                "customer_entity_id" => $magentoCustomer->getId()
            ]);
        } catch (\Exception $e) {
            $this->eventDispatcher->dispatch(self::EVENT_MAP_GIGYA_TO_MAGENTO_FAILURE, [
                "gigya_uid" => $gigyaAccountData->getUID(),
                "customer_entity_id" => $magentoCustomer->getId()
            ]);
            if (!$this->processEventMapGigyaToMagentoException($e, $magentoCustomer, $gigyaAccountData,
                $gigyaAccountLoggingEmail)
            ) {
                throw new GigyaFieldMappingException($e);
            }
        }

        return $magentoCustomer;
    }

	/**
	 * Saves the Customer entity in database
	 *
	 * @param \Magento\Customer\Model\Backend\Customer $magentoCustomer $magentoCustomer
	 *
	 * @throws \Magento\Framework\Exception\InputException
	 * @throws \Magento\Framework\Exception\LocalizedException
	 * @throws \Magento\Framework\Exception\State\InputMismatchException
	 */
    public function saveMagentoCustomer($magentoCustomer) {
        $this->customerRepository->save($magentoCustomer->getDataModel());
    }

	/**
	 * Will synchronize Magento account entity with Gigya account if needed.
	 *
	 * @param Observer $observer
	 *
	 * @return void
	 *
	 * @throws GSException
	 * @throws \Exception
	 * @throws \Magento\Framework\Exception\InputException
	 * @throws \Magento\Framework\Exception\LocalizedException
	 * @throws \Magento\Framework\Exception\State\InputMismatchException
	 */
    public function execute(Observer $observer)
    {
        $this->logger->debug("Update customer Gigya => Magento on event " . $observer->getEvent()->getName());

		/** @var \Magento\Customer\Model\Backend\Customer $magentoCustomer */
        $magentoCustomer = $observer->getData('customer');
        if (empty($magentoCustomer->getGigyaAccountEnriched())) {
            $magentoCustomer->setGigyaAccountEnriched(false);
        }

        if ($this->shallEnrichMagentoCustomerWithGigyaAccount($magentoCustomer, $observer->getEvent()->getName())) {
            $key = $this->enricherCustomerRegistry->getCustomerRegistryKey($magentoCustomer);
            $this->enrichedCustomers[$key] = 1;
            $this->enricherCustomerRegistry->pushRegisteredCustomer($magentoCustomer);

            try {
                $gigyaData = $this->getGigyaDataForEnrichment($magentoCustomer);
                $magentoCustomer = $this->enrichMagentoCustomerWithGigyaData(
                    $magentoCustomer, $gigyaData['gigya_user'],
                    $gigyaData['gigya_logging_email']
                );
                $magentoCustomer->setGigyaAccountEnriched(true);
                $customerEntityId = $magentoCustomer->getId();
                $excludeSyncCms2G = true;
                if (!$this->gigyaSyncHelper->isCustomerIdExcludedFromSync($customerEntityId, GigyaSyncHelper::DIR_CMS2G)) {
                    // We prevent synchronizing the M2 customer data to the Gigya account : that should be done only on explicit customer save,
                    // here the very first action is to load the M2 customer
                    $this->gigyaSyncHelper->excludeCustomerIdFromSync($magentoCustomer->getId(), GigyaSyncHelper::DIR_CMS2G);
                    $excludeSyncCms2G = false;
                }

                try {
                    $this->saveMagentoCustomer($magentoCustomer);
                } finally {
                    // If the synchro to Gigya was not already disabled we re-enable it
                    if (!$excludeSyncCms2G) {
                        $this->gigyaSyncHelper->undoExcludeCustomerIdFromSync($magentoCustomer->getId(),
                            GigyaSyncHelper::DIR_CMS2G);
                    }
                }
            } catch(GSApiException $e) {
                $this->logger->error('Could not update Magento customer account with Gigya data due to Gigya service call error', [
                    'customer_entity_id' => $magentoCustomer->getEntityId(),
                    'gigya_uid' => $magentoCustomer->getGigyaUid(),
                    'exception' => $e
                ]);
            }

            $this->enricherCustomerRegistry->removeRegisteredCustomer($magentoCustomer);
        }
    }
}

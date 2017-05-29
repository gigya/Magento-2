<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\ManagerInterface;
use \Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * AbstractMagentoCustomerEnricher
 *
 * Will enrich a Magento customer entity's fields with the Gigya account data.
 *
 * @see GigyaAccountRepositoryInterface
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
abstract class AbstractMagentoCustomerEnricher implements ObserverInterface
{
    /**
     * This event is dispatched when the enrichment has been done
     */
    const EVENT_POST_SYNC_FROM_GIGYA = 'post_sync_from_gigya';

    /**
     * Array to push customer entities once they've been enriched. We will avoid to enrich several time the same instance by checking this registry.
     *
     * @var array $customerRegistry
     */
    private $customerRegistry = [];

    /** @var  GigyaAccountRepositoryInterface */
    protected $gigyaAccountRepository;

    /** @var  GigyaSyncHelper */
    protected $gigyaSyncHelper;

    /** @var ManagerInterface */
    protected $eventDispatcher;

    /** @var  LoggerInterface */
    protected $logger;

    /**
     * AbstractMagentoCustomerEnricher constructor.
     *
     * @param GigyaAccountRepositoryInterface $gigyaAccountRepository
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param ManagerInterface $eventDispatcher
     * @param LoggerInterface $logger
     */
    public function __construct(
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        ManagerInterface $eventDispatcher,
        LoggerInterface $logger
    ) {
        $this->gigyaAccountRepository = $gigyaAccountRepository;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Check if Magento customer entity must be enriched with the Gigya's account data.
     *
     * @param Customer $magentoCustomer
     * @return bool True if the customer is not null, not flagged as deleted, and not flagged has already synchronized.
     */
    protected function shallUpdateMagentoCustomerWithGigyaAccount($magentoCustomer)
    {
        $result = $magentoCustomer != null && !$magentoCustomer->isDeleted();

        $registeredCustomer = $this->retrieveRegisteredCustomer($magentoCustomer);
        if ($registeredCustomer != null) {
            $result = empty($registeredCustomer->getIsSynchronizedFromGigya()) || !$registeredCustomer->getIsSynchronizedFromGigya();
        }

        return $result;
    }

    /**
     * Method called if an exception is caught when dispatching event AbstractMagentoCustomerEnricher::EVENT_POST_SYNC_FROM_GIGYA
     *
     * Default behavior is to log a warning (exception is muted)
     *
     * @param $e \Exception
     * @param $magentoCustomer Customer
     * @param $gigyaAccountData GigyaUser
     * @param $gigyaAccountLoggingEmail string
     */
    protected function processEventPostSyncFromGigyaException($e, $magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail) {

        // Ignore : enrichment shall not fail on third party code exception
        $this->logger->warning(
            'Exception raised when enriching Magento customer with Gigya data.',
            [
                'exception' => $e,
                'customer_entity_id' => ($magentoCustomer != null) ? $magentoCustomer->getEntityId() : 'customer is null',
                'gigya_uid' => ($gigyaAccountData != null) ? $gigyaAccountData->getUID() : 'Gigya data are null',
                'gigya_logging_email' => $gigyaAccountLoggingEmail
            ]
        );
    }

    /**
     * Performs the enrichment of the customer with the Gigya data.
     *
     * @param $magentoCustomer Customer
     * @return void
     */
    protected function enrichMagentoCustomer($magentoCustomer)
    {
        $gigyaAccountData = $this->gigyaAccountRepository->get($magentoCustomer->getGigyaUid());
        $gigyaAccountLoggingEmail = $this->gigyaSyncHelper->getMagentoCustomerAndLoggingEmail($gigyaAccountData)['logging_email'];
        $this->gigyaSyncHelper->updateMagentoCustomerWithGygiaRequiredFields($magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail);

        $magentoCustomer->setIsSynchronizedFromGigya(true);
        $this->pushRegisteredCustomer($magentoCustomer);

        try {
            $this->eventDispatcher->dispatch(self::EVENT_POST_SYNC_FROM_GIGYA, [
                "gigya_user" => $gigyaAccountData,
                "customer" => $magentoCustomer
            ]);
        } catch(\Exception $e) {
            $this->processEventPostSyncFromGigyaException($e, $magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail);
        }
    }

    /**
     * Will synchronize Magento account entity with Gigya account if needed.
     *
     * @param Observer $observer Must hang a data 'customer' of type Magento\Customer\Model\Customer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Customer $customer */
        $magentoCustomer = $observer->getData('customer');

        if ($this->shallUpdateMagentoCustomerWithGigyaAccount($magentoCustomer)) {

            $this->enrichMagentoCustomer($magentoCustomer);
        }
    }

    /**
     * Get the key used to store the customer in the registry ($customerRegistry)
     *
     * @param $customer
     * @return string Concatenation of websiteId|gigyaUid
     */
    private function getCustomerRegistryKey($customer)
    {
        return $customer->getWebsiteId().'|'.$customer->getGigyaUid();
    }

    /**
     * Get a customer already pushed in the registry ($customerRegistry), if any.
     *
     * @param Customer $customer
     * @return Customer|null
     */
    private function retrieveRegisteredCustomer($customer)
    {
        $key = $this->getCustomerRegistryKey($customer);
        $result = (array_key_exists($key, $this->customerRegistry)) ? $this->customerRegistry[$key] : null;

        return $result;
    }

    /**
     * Push a customer in the registry ($customerRegistry)
     *
     * @param $customer
     */
    private function pushRegisteredCustomer($customer)
    {
        $this->customerRegistry[$this->getCustomerRegistryKey($customer)] = $customer;
    }
}
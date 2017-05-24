<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Model\Customer;
use \Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * AbstractMagentoCustomerEnricher
 *
 * Will enrich a Magento customer entity's fields with the Gigya account data fetched from the Gigya service.
 *
 * @see GigyaAccountServiceInterface
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
abstract class AbstractMagentoCustomerEnricher implements ObserverInterface
{
    /**
     * Array to push customer entities once they've been enriched. We will avoid to enrich several time the same instance by checking this registry.
     *
     * @var array $customerRegistry
     */
    private $customerRegistry = [];

    /** @var  GigyaAccountServiceInterface */
    protected $gigyaAccountService;

    /** @var  GigyaSyncHelper */
    protected $gigyaSyncHelper;

    /**
     * AbstractMagentoCustomerEnricher constructor.
     *
     * @param GigyaAccountServiceInterface $gigyaAccountService
     * @param GigyaSyncHelper $gigyaSyncHelper
     */
    public function __construct(
        GigyaAccountServiceInterface $gigyaAccountService,
        GigyaSyncHelper $gigyaSyncHelper
    ) {
            $this->gigyaAccountService = $gigyaAccountService;
            $this->gigyaSyncHelper = $gigyaSyncHelper;
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
     * Performs the enrichment of the customer with the Gigya data fetched from the Gigya service.
     *
     * @param $magentoCustomer Customer
     * @return void
     */
    protected function enrichMagentoCustomer($magentoCustomer)
    {
        $gigyaAccountData = $this->gigyaAccountService->get($magentoCustomer->getGigyaUid());
        $gigyaAccountLoggingEmail = $this->gigyaSyncHelper->getMagentoCustomerAndLoggingEmail($gigyaAccountData)['logging_email'];
        $this->gigyaSyncHelper->updateMagentoCustomerWithGygiaAccount($magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail);

        $magentoCustomer->setIsSynchronizedFromGigya(true);
        $this->pushRegisteredCustomer($magentoCustomer);
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
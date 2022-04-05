<?php

namespace Gigya\GigyaIM\Observer;


use Magento\Customer\Model\Customer;

/**
 * EnricherCustomerRegistry
 *
 * Class for enrichers Gigya data from / to Magento customer.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 * It exposes facilities to keep in memory accounts that have been enriched,
 * whatever the direction is (Gigya to or from M2),
 * so that we can avoid to loop on enrichment process for an account that has already been enriched.
 *
 * The accounts are kept in memory during the same server call only : a subsequent call will reset this registry.
 *
 */
class EnricherCustomerRegistry
{
    /**
     * Array to push customer entities once they've been synchronized from / to Gigya.
     *
     * We will avoid enriching the same instance several times by checking this registry.
     *
     * @var array $customerRegistry
     */
    private $customerRegistry = [];

    /**
     * Get the key used to store the customer in the registry ($customerRegistry)
     *
     * @param Customer $customer
     * @return string Concatenation of websiteId|gigyaUid
     */
    public function getCustomerRegistryKey($customer)
    {
        return $customer->getWebsiteId().'|'.$customer->getGigyaUid();
    }

    /**
     * Get a customer already pushed in the registry ($customerRegistry), if any.
     *
     * @param Customer $customer
     * @return Customer|null
     */
    public function retrieveRegisteredCustomer($customer)
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
    public function pushRegisteredCustomer($customer)
    {
        $this->customerRegistry[$this->getCustomerRegistryKey($customer)] = $customer;
    }

    public function removeRegisteredCustomer($customer)
    {
        $key = $this->getCustomerRegistryKey($customer);
        unset($this->customerRegistry[$key]);

        return $this;
    }
}

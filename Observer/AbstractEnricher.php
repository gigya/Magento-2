<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;


use Magento\Customer\Model\Customer;

/**
 * AbstractEnricher
 *
 * Base class for enrichers Gigya data from / to Magento customer.
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
class AbstractEnricher
{
    /**
     * Array to push customer entities once they've been synchronized from / to Gigya.
     *
     * We will avoid to enrich several time the same instance by checking this registry.
     *
     * @var array $customerRegistry
     */
    private $customerRegistry = [];

    /**
     * Get the key used to store the customer in the registry ($customerRegistry)
     *
     * @param $customer
     * @return string Concatenation of websiteId|gigyaUid
     */
    protected function getCustomerRegistryKey($customer)
    {
        return $customer->getWebsiteId().'|'.$customer->getGigyaUid();
    }

    /**
     * Get a customer already pushed in the registry ($customerRegistry), if any.
     *
     * @param Customer $customer
     * @return Customer|null
     */
    protected function retrieveRegisteredCustomer($customer)
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
    protected function pushRegisteredCustomer($customer)
    {
        $this->customerRegistry[$this->getCustomerRegistryKey($customer)] = $customer;
    }
}
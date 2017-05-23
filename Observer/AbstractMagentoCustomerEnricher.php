<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerRegistry;
use \Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * AbstractMagentoCustomerEnricher
 *
 * Will enrich a Magento customer entity's fields with the Gigya account data.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
abstract class AbstractMagentoCustomerEnricher implements ObserverInterface
{
    /**
     * Performs the enrichment of the customer with the Gigya data.
     *
     * @param $magentoCustomer Customer
     * @return void
     */
    protected abstract function enrichMagentoCustomer($magentoCustomer);

    /** @var  CustomerRegistry */
    protected $customerRegistry;

    /**
     * AbstractMagentoCustomerEnricher constructor.
     *
     * @param CustomerRegistry $customerRegistry
     */
    public function __construct(
        CustomerRegistry $customerRegistry
    ) {
        $this->customerRegistry = $customerRegistry;
    }

    /**
     * Check if Magento customer entity must be enriched with the Gigya's account data.
     *
     * @param Customer $magentoCustomer
     * @return bool True if the customer is not null, not flagged as deleted, and not flagged has already synchronized.
     */
    public function shallUpdateMagentoCustomerWithGigyaAccount($magentoCustomer)
    {
        $result = $magentoCustomer != null && !$magentoCustomer->isDeleted();

        if ($result) {
            $registeredCustomer = $this->customerRegistry->retrieveByEmail($magentoCustomer->getEmail());
            if ($registeredCustomer != null) {
                $result = empty($registeredCustomer->getIsSynchronizedFromGigya()) || !$registeredCustomer->getIsSynchronizedFromGigya();
            }
        }

        return $result;
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
            $magentoCustomer->setIsSynchronizedFromGigya(true);
            $this->customerRegistry->push($magentoCustomer);
        }
    }
}
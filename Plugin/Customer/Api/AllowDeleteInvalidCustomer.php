<?php

namespace Gigya\GigyaIM\Plugin\Customer\Api;

/**
 * Class AllowDeleteInvalidCustomer
 *
 * Will prevent syncing customers that are about to be deleted on Magento, allowing one to delete invalid customers
 * (with an invalid or void GUID)
 *
 * @author akhayrullin <info@x2i.fr>
 *
 * @package Gigya\GigyaIM\Plugin\Customer\Api
 */
class AllowDeleteInvalidCustomer
{
    /**
     * @var \Gigya\GigyaIM\Helper\GigyaSyncHelper
     */
    protected $gigyaSyncHelper;

    /**
     * @param \Gigya\GigyaIM\Helper\GigyaSyncHelper $gigyaSyncHelper
     */
    public function __construct(
        \Gigya\GigyaIM\Helper\GigyaSyncHelper $gigyaSyncHelper
    )
    {
        $this->gigyaSyncHelper = $gigyaSyncHelper;
    }

    /**
     * Prevents syncing a customer that is about to be deleted
     *
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $subject
     * @param \Closure $proceed
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @return bool
     */
    public function aroundDelete(
        \Magento\Customer\Api\CustomerRepositoryInterface $subject,
        \Closure $proceed,
        \Magento\Customer\Api\Data\CustomerInterface $customer
    )
    {
        $customerId = $customer->getId();
        $this->gigyaSyncHelper->excludeCustomerIdFromSync($customerId);
        $return = $proceed($customer);
        $this->gigyaSyncHelper->undoExcludeCustomerIdFromSync($customerId);
        return $return;
    }

    /**
     * Prevents syncing a customer that is about to be deleted
     *
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $subject
     * @param \Closure $proceed
     * @param int $customerId
     * @return bool
     */
    public function aroundDeleteById(
        \Magento\Customer\Api\CustomerRepositoryInterface $subject,
        \Closure $proceed, $customerId
    )
    {
        $this->gigyaSyncHelper->excludeCustomerIdFromSync($customerId);
        $return = $proceed($customerId);
        $this->gigyaSyncHelper->undoExcludeCustomerIdFromSync($customerId);
        return $return;
    }
}
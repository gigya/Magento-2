<?php

namespace Gigya\GigyaIM\Plugin\Customer\Api;

use Closure;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * Class AllowDeleteInvalidCustomer
 *
 * Will prevent syncing customers that are about to be deleted on Magento, allowing one to delete invalid customers
 * (with an invalid or void GUID)
 *
 * @author akhayrullin <info@x2i.fr>
 *
 */
class AllowDeleteInvalidCustomer
{
    /**
     * @var GigyaSyncHelper
     */
    protected $gigyaSyncHelper;

    /**
     * @param GigyaSyncHelper $gigyaSyncHelper
     */
    public function __construct(
        GigyaSyncHelper $gigyaSyncHelper
    ) {
        $this->gigyaSyncHelper = $gigyaSyncHelper;
    }

    /**
     * Prevents syncing a customer that is about to be deleted
     *
     * @param CustomerRepositoryInterface $subject
     * @param Closure $proceed
     * @param CustomerInterface $customer
     * @return bool
     */
    public function aroundDelete(
        CustomerRepositoryInterface $subject,
        Closure $proceed,
        CustomerInterface $customer
    ) {
        $customerId = $customer->getId();
        $this->gigyaSyncHelper->excludeCustomerIdFromSync($customerId);
        $return = $proceed($customer);
        $this->gigyaSyncHelper->undoExcludeCustomerIdFromSync($customerId);
        return $return;
    }

    /**
     * Prevents syncing a customer that is about to be deleted
     *
     * @param CustomerRepositoryInterface $subject
     * @param Closure $proceed
     * @param int $customerId
     * @return bool
     */
    public function aroundDeleteById(
        CustomerRepositoryInterface $subject,
        Closure $proceed,
        $customerId
    ) {
        $this->gigyaSyncHelper->excludeCustomerIdFromSync($customerId);
        $return = $proceed($customerId);
        $this->gigyaSyncHelper->undoExcludeCustomerIdFromSync($customerId);
        return $return;
    }
}

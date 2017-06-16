<?php

namespace Gigya\GigyaIM\Block\Adminhtml\Customer\Edit\Tab\View;

use Magento\Customer\Block\Adminhtml\Edit\Tab\View\PersonalInfo;

class GigyaUID extends \Magento\Backend\Block\Template
{
    /**
     * Extract the Gigya ID from the customer present in the parent block.
     *
     * @return bool|string
     */
    public function getGUIDFromParentBlock()
    {
        $customer = $this->getCustomerFromParentBlock();
        if($customer)
        {
            $attribute = $customer->getCustomAttribute('gigya_uid');
            if($attribute)
            {
                return $attribute->getValue();
            }
        }
        return false;
    }

    /**
     * Extract the current customer from the parent block.
     *
     * @return bool|\Magento\Customer\Api\Data\CustomerInterface
     */
    protected function getCustomerFromParentBlock()
    {
        $parentBlock = $this->getParentBlock();
        if($parentBlock && $parentBlock instanceof PersonalInfo)
        {
            return $parentBlock->getCustomer();
        }
        return false;
    }
}
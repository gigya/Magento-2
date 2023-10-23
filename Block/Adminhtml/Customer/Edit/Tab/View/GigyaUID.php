<?php

namespace Gigya\GigyaIM\Block\Adminhtml\Customer\Edit\Tab\View;

use Magento\Backend\Block\Template;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Block\Adminhtml\Edit\Tab\View\PersonalInfo;

class GigyaUID extends Template
{
    /**
     * Extract the Gigya ID from the customer present in the parent block.
     *
     * @return bool|string
     */
    public function getGUIDFromParentBlock(): bool|string
    {
        $customer = $this->getCustomerFromParentBlock();
        if ($customer) {
            $attribute = $customer->getCustomAttribute('gigya_uid');
            if ($attribute) {
                return $attribute->getValue();
            }
        }

        return false;
    }

    /**
     * Extract the current customer from the parent block.
     *
     * @return bool|CustomerInterface
     */
    protected function getCustomerFromParentBlock(): bool|CustomerInterface
    {
        $parentBlock = $this->getParentBlock();
        if ($parentBlock && $parentBlock instanceof PersonalInfo) {
            return $parentBlock->getCustomer();
        }

        return false;
    }
}

<?php

namespace Gigya\GigyaIM\Block\Adminhtml\Customer\Edit\Tab\View;

use Magento\Customer\Block\Adminhtml\Edit\Tab\View\PersonalInfo;

class GigyaUID extends \Magento\Backend\Block\Template
{
    public function getCustomerFromParentBlock()
    {
        $parentBlock = $this->getParentBlock();
        if($parentBlock && $parentBlock instanceof PersonalInfo)
        {
            return $parentBlock->getCustomer();
        }
        return false;
    }
}
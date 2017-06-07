<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Test\Observer;


use Gigya\CmsStarterKit\user\GigyaProfile;
use Magento\Framework\Event\ObserverInterface;

class TestGigyaSyncFieldMapping implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /* @var $customerData \Magento\Framework\DataObject */
        $customerData = $observer->getEvent()->getDataObject();
        $data = $customerData->getData('customer_data');
        if(isset($data['gender']))
        {

            switch($data['gender'])
            {
                case '1':
                    $data['gender'] = 'm';
                    break;
                case '2':
                    $data['gender'] = 'f';
                    break;
                case '3':
                    $data['gender'] = 'u';
                    break;
                default:
                    $data['gender'] = null;
                    break;
            }
            $customerData->setData('customer_data', $data);
        }

        return $this;
    }
}
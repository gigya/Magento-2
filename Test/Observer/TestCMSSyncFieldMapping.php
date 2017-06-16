<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Test\Observer;


use Gigya\CmsStarterKit\user\GigyaProfile;
use Magento\Framework\Event\ObserverInterface;

class TestCMSSyncFieldMapping implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var GigyaProfile $gigyaProfile */
        $gigyaProfile = $observer->getData('gigya_user')->getProfile();
        switch($gigyaProfile->getGender()) {
            case 'm':
                $gigyaProfile->setGender('1');
                break;

            case 'f':
                $gigyaProfile->setGender('2');
                break;

            default:
                $gigyaProfile->setGender('3');
        }
    }
}
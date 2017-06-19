<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;


use Gigya\CmsStarterKit\user\GigyaProfile;
use Magento\Framework\Event\ObserverInterface;

/**
 * DefaultCMSSyncFieldMapping
 *
 * Default g2cms field mapping implementation. For now only attribute gender.
 *
 * To be effective one have to declare this observer on event 'gigya_pre_field_mapping'.
 *
 * @author      vlemaire <info@x2i.fr>
 */
class DefaultCMSSyncFieldMapping implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var GigyaProfile $gigyaProfile */
        $gigyaProfile = $observer->getData('gigya_user')->getProfile();
        // 'Translate' the gender code from Gigya to Magento value
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
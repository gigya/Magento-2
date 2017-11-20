<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;


use Gigya\CmsStarterKit\User\GigyaProfile;
use Magento\Framework\Event\ObserverInterface;

/**
 * DefaultCMSSyncFieldMapping
 *
 * Default g2cms field mapping implementation. For now only attributes gender and date of birth (dob)
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

        /* @var \Magento\Customer\Model\Customer $customer */
        $customer = $observer->getData('customer');
        // 'Translate' the gender code from Gigya to Magento value
        switch($gigyaProfile->getGender()) {
            case 'm':
                $customer->setGender('1');
                break;

            case 'f':
                $customer->setGender('2');
                break;

            default:
                $customer->setGender('3');
        }

        $birthDay = $gigyaProfile->getBirthDay();
        $birthMonth = $gigyaProfile->getBirthMonth();
        $birthYear = $gigyaProfile->getBirthYear();

        if($birthDay && $birthMonth && $birthYear)
        {
            $customer->setDob(sprintf('%s-%s-%s', $birthYear, str_pad($birthMonth, 2, "0", STR_PAD_LEFT), str_pad($birthDay, 2, "0", STR_PAD_LEFT)));
        }
    }
}
<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;


use Gigya\CmsStarterKit\user\GigyaProfile;
use Magento\Customer\Model\Data\Customer;
use Magento\Framework\Event\ObserverInterface;

/**
 * DefaultCMSSyncFieldMapping
 *
 * Default cms2g field mapping implementation. For now only attributes gender and date of birth (BirthDay BirthMonth BirthYear)
 *
 * To be effective one have to declare this observer on event 'pre_sync_to_gigya'.
 *
 * @author      vlemaire <info@x2i.fr>
 */
class DefaultGigyaSyncFieldMapping implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var Customer $magentoCustomer */
        $magentoCustomer = $observer->getData('customer');
        /** @var GigyaProfile $gigyaProfile */
        $gigyaProfile = $observer->getData('gigya_user')->getProfile();
        // 'Translate' the gender code from Magento to Gigya value
        switch($magentoCustomer->getGender()) {
            case 1:
                $gigyaProfile->setGender('m');
                break;

            case 2:
                $gigyaProfile->setGender('f');
                break;

            default:
                $gigyaProfile->setGender('u');
        }
        // 'Translate' the date of birth code from Gigya to Magento value
        $dob = $magentoCustomer->getDob();

        if ($dob != null && trim($dob) != '') {

            $date = new \Zend_Date($dob, 'YYYY-MM-dd');
            $birthYear = (int)$date->get(\Zend_Date::YEAR);
            $birthMonth = (int)$date->get(\Zend_Date::MONTH);
            $birthDay = (int)$date->get(\Zend_Date::DAY);

            $gigyaProfile->setBirthDay($birthDay);
            $gigyaProfile->setBirthMonth($birthMonth);
            $gigyaProfile->setBirthYear($birthYear);
        }

        // 'Translate' the subscribe boolean code from Gigya to Magento value
        if($magentoCustomer->getCustomAttribute('gigya_subscribe')->getValue() == '0'){
            $magentoCustomer->setCustomAttribute('gigya_subscribe','false');
        }
        if($magentoCustomer->getCustomAttribute('gigya_subscribe')->getValue() == '1'){
            $magentoCustomer->setCustomAttribute('gigya_subscribe','true');
        }
    }
}
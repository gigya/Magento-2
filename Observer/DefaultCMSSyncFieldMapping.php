<?php

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaProfile;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\Data\Customer;
use Magento\Framework\Event\Observer;
use Gigya\GigyaIM\Model\Config as GigyaConfig;

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
	/** @var GigyaConfig */
	protected $config;

	public function __construct(GigyaConfig $config) {
		$this->config = $config;
	}

	/**
     * Method execute
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
    	if ($this->config->isGigyaEnabled())
		{
			/** @var GigyaUser $gigyaUser */
			$gigyaUser = $observer->getData('gigya_user');

			/** @var GigyaProfile $gigyaProfile */
			$gigyaProfile = $gigyaUser->getProfile();
			/** @var Customer $customer */
			$customer = $observer->getData('customer');

			// 'Translate' the gender code from Gigya to Magento value
			switch ($gigyaProfile->getGender()) {
				case 'm':
					$customer->setGender('1');
					break;

				case 'f':
					$customer->setGender('2');
					break;

				default:
					$customer->setGender('3');
			}

			/* 'Translate' the date of birth code from Gigya to Magento value */
			$birthDay = $gigyaProfile->getBirthDay();
			$birthMonth = $gigyaProfile->getBirthMonth();
			$birthYear = $gigyaProfile->getBirthYear();

			if ($birthDay && $birthMonth && $birthYear) {
				$customer->setDob(
					sprintf(
						'%s-%s-%s',
						$birthYear,
						str_pad($birthMonth, 2, '0', STR_PAD_LEFT),
						str_pad($birthDay, 2, '0', STR_PAD_LEFT)
					)
				);
			}

			// 'Translate' the subscribe boolean code from Gigya to Magento value
			$customerData = $gigyaUser->getData('subscribe');
			if (isset($customerData['subscribe'])) {
				if ($customerData['subscribe'] === 'false') {
					$gigyaUser->setData(array_merge($customerData, ['subscribe' => 0, 'data' => ['subscribe' => 0]]));
				}
				if ($customerData['subscribe'] === 'true') {
					$gigyaUser->setData(array_merge($customerData, ['subscribe' => 1, 'data' => ['subscribe' => 1]]));
				}
			}
		}
    }
}
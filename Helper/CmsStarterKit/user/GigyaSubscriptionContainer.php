<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\user;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaJsonObject;

class GigyaSubscriptionContainer extends GigyaJsonObject
{
	/**
	 * @var GigyaSubscription
	 */
	private $email;

	/**
	 * @return GigyaSubscription
	 */
	public function getEmail() {
		return $this->email;
	}

	/**
	 * @param GigyaSubscription $email
	 */
	public function setEmail($email) {
		$this->email = $email;
	}

	/**
	 * @return array|null
	 */
	public function getSubscriptionAsArray() {
		$result = null;

		if ($this->getEmail())
		{
			$result = $this->getEmail()->asArray();
		}

		return $result;
	}
}
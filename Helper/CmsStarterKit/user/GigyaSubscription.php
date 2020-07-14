<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\user;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaJsonObject;

class GigyaSubscription extends GigyaJsonObject
{
	/**
	 * @var boolean
	 */
	private $isSubscribed;

	/**
	 * @var array
	 */
	private $tags;

	/**
	 * @var string
	 */
	private $lastUpdatedSubscriptionState;

	/**
	 * @var GigyaSubscriptionDoubleOptIn
	 */
	private $doubleOptIn;

	/**
	 * @return boolean
	 */
	public function getIsSubscribed() {
		return $this->isSubscribed;
	}

	/**
	 * @param boolean $isSubscribed
	 */
	public function setIsSubscribed($isSubscribed) {
		$this->isSubscribed = $isSubscribed;
	}

	/**
	 * @return array
	 */
	public function getTags() {
		return $this->tags;
	}

	/**
	 * @param string|array $tags
	 */
	public function setTags($tags) {
		if (is_string($tags))
		{
			$tags = json_decode($tags);
		}
		$this->tags = $tags;
	}

	/**
	 * @return string
	 */
	public function getLastUpdatedSubscriptionState() {
		return $this->lastUpdatedSubscriptionState;
	}

	/**
	 * @param string $lastUpdatedSubscriptionState
	 */
	public function setLastUpdatedSubscriptionState($lastUpdatedSubscriptionState) {
		$this->lastUpdatedSubscriptionState = $lastUpdatedSubscriptionState;
	}

	/**
	 * @return GigyaSubscriptionDoubleOptIn
	 */
	public function getDoubleOptIn() {
		return $this->doubleOptIn;
	}

	/**
	 * @param GigyaSubscriptionDoubleOptIn|array $doubleOptIn
	 */
	public function setDoubleOptIn($doubleOptIn) {
		if (is_array($doubleOptIn))
		{
			$doubleOptInObject = new GigyaSubscriptionDoubleOptIn(null);

			/** @var array $doubleOptIn */
			foreach ($doubleOptIn as $key => $value)
			{
				$methodName   = 'set' . ucfirst($key);
				$methodParams = $value;
				$doubleOptInObject->$methodName($methodParams);
			}
		}
		else
		{
			$doubleOptInObject = $doubleOptIn;
		}

		$this->doubleOptIn = $doubleOptInObject;
	}

	/**
	 * @return array|null
	 */
	public function getDoubleOptInAsArray() {
		$result = null;

		if ($this->getDoubleOptIn())
		{
			$result = $this->getDoubleOptIn()->asArray();
		}

		return $result;
	}

	/**
	 * @return array
	 */
	public function asArray() {
		return [
			'isSubscribed'                 => $this->getIsSubscribed(),
			'tags'                         => $this->getTags(),
			'lastUpdatedSubscriptionState' => $this->getLastUpdatedSubscriptionState(),
			'doubleOptIn'                  => $this->getDoubleOptInAsArray(),
		];
	}
}
<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\user;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaJsonObject;

class GigyaSubscriptionDoubleOptIn extends GigyaJsonObject
{
	/**
	 * @var string
	 */
	private $emailSentTime;

	/**
	 * @var string
	 */
	private $confirmTime;

	/**
	 * @var string
	 */
	private $status;

	/**
	 * @return string
	 */
	public function getEmailSentTime() {
		return $this->emailSentTime;
	}

	/**
	 * @param string $emailSentTime
	 */
	public function setEmailSentTime($emailSentTime) {
		$this->emailSentTime = $emailSentTime;
	}

	/**
	 * @return string
	 */
	public function getConfirmTime() {
		return $this->confirmTime;
	}

	/**
	 * @param string $confirmTime
	 */
	public function setConfirmTime($confirmTime) {
		$this->confirmTime = $confirmTime;
	}

	/**
	 * @return string
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @param string $status
	 */
	public function setStatus($status) {
		$this->status = $status;
	}

	/**
	 * @return array
	 */
	public function asArray() {
		return [
			'emailSentTime' => $this->getEmailSentTime(),
			'confirmTime'   => $this->getConfirmTime(),
			'status'        => $this->getStatus(),
		];
	}
}
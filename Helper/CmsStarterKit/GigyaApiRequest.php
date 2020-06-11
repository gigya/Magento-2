<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit;

use Gigya\PHP\GSException;
use Gigya\PHP\GSObject;
use Gigya\PHP\GSRequest;
use Gigya\PHP\GSResponse;

class GigyaApiRequest extends GSRequest
{
	/**
	 * @param null $timeout
	 *
	 * @return GSResponse
	 *
	 * @throws GSException
	 * @throws GSApiException
	 */
	public function send($timeout = null) {
		$res = parent::send($timeout);
		if ($res->getErrorCode() == 0)
		{
			return $res;
		}

		if (!empty($res->getData())) {
			throw new GSApiException($res->getErrorMessage(), $res->getErrorCode(), $res->getResponseText(), $res->getString("callId", "N/A"));
		} else {
			throw new GSException($res->getErrorMessage(), $res->getErrorCode());
		}
	}

	/**
	 * GSRequestNg constructor.
	 *
	 * @param string   $apiKey
	 * @param string   $secret
	 * @param string   $apiMethod
	 * @param GSObject $params
	 * @param string   $dataCenter
	 * @param boolean  $useHTTPS
	 * @param null     $userKey
	 *
	 * @throws \Exception
	 */
	public function __construct($apiKey, $secret, $apiMethod, $params, $dataCenter, $useHTTPS = true, $userKey = null) {
		parent::__construct($apiKey, $secret, $apiMethod, $params, $useHTTPS, $userKey);
		$this->setAPIDomain($dataCenter);
	}
}
<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\sdk;

class GigyaApiRequest extends GSRequest
{
	/**
	 * @param null $timeout
	 *
	 * @return GSResponse
	 *
	 * @throws \Exception
	 * @throws GSApiException
	 */
	public function send($timeout = null) {
		$res = parent::send($timeout);
		if ($res->getErrorCode() == 0)
		{
			return $res;
		}

		throw new GSApiException($res->getErrorMessage(), $res->getErrorCode(), $res->getResponseText(), $res->getString("callId", "N/A"));
	}

	/**
	 * GSRequestNg constructor.
	 *
	 * @param string   $apiKey
	 * @param string   $secret
	 * @param string   $apiMethod
	 * @param GSObject $params
	 * @param string   $dataCenter
	 * @param bool     $useHTTPS
	 * @param null     $userKey
	 *
	 * @throws \Exception
	 */
	public function __construct($apiKey, $secret, $apiMethod, $params, $dataCenter, $useHTTPS = true, $userKey = null) {
		parent::__construct($apiKey, $secret, $apiMethod, $params, $useHTTPS, $userKey);
		$this->setAPIDomain($dataCenter);
		$this->setCAFile(realpath(dirname(__FILE__) . "/cacert.pem"));
	}
}
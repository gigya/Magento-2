<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit;

use Gigya\PHP\GSException;
use Gigya\PHP\GSObject;
use Gigya\PHP\GSRequest;
use Gigya\PHP\GSResponse;
use Gigya\PHP\GSKeyNotFoundException;

class GigyaAuthRequest extends GSRequest
{
	/**
	 * GSApiRequest constructor.
	 *
	 * @param string $apiKey
	 * @param string $privateKey
	 * @param string $apiMethod
	 * @param GSObject $params
	 * @param string $dataCenter
	 * @param bool $useHTTPS
	 * @param string $userKey
	 *
	 * @throws GSKeyNotFoundException
	 */
	public function __construct($apiKey, $privateKey, $apiMethod, $params, $dataCenter, $useHTTPS = true, $userKey = null) {
		parent::__construct($apiKey, null, $apiMethod, $params, $useHTTPS, $userKey, $privateKey);
		$this->setAPIDomain($dataCenter);
	}

	/**
	 * @param int $timeout
	 *
	 * @return GSResponse
	 *
	 * @throws GSException
	 * @throws GSApiException
	 * @throws GSKeyNotFoundException
	 */
	public function send($timeout = null) {
		$res = parent::send($timeout);

		if ($res->getErrorCode() == 0)
		{
			return $res;
		}

		if (!empty($res->getData())) { /* Actual error response from Gigya */
			throw new GSApiException($res->getErrorMessage(), $res->getErrorCode(), $res->getResponseText(), $res->getString("callId", "N/A"));
		} else { /* Hard-coded error in PHP SDK, or another failure */
			throw new GSException($res->getErrorMessage(), $res->getErrorCode());
		}
	}
}
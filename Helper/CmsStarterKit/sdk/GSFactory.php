<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\sdk;

class GSFactory
{
	/**
	 * @param        $apiKey
	 * @param        $secret
	 * @param        $apiMethod
	 * @param        $params
	 * @param string $dataCenter
	 * @param bool   $useHTTPS
	 *
	 * @return GigyaApiRequest
	 *
	 * @throws \Exception
	 */
	public static function createGsRequest($apiKey, $secret, $apiMethod, $params, $dataCenter = "us1.gigya.com", $useHTTPS = true) {
		return new GigyaApiRequest($apiKey, $secret, $apiMethod, $params, $dataCenter, $useHTTPS);
	}

	/**
	 * @param string   $apiKey
	 * @param string   $key
	 * @param string   $secret
	 * @param string   $apiMethod
	 * @param GSObject $params
	 * @param string   $dataCenter
	 * @param bool     $useHTTPS
	 *
	 * @return GigyaApiRequest
	 *
	 * @throws \Exception
	 */
	public static function createGSRequestAppKey($apiKey, $key, $secret, $apiMethod, $params, $dataCenter = "us1.gigya.com", $useHTTPS = true) {
		return new GigyaApiRequest($apiKey, $secret, $apiMethod, $params, $dataCenter, $useHTTPS, $key);
	}

	/**
	 * @param string   $token
	 * @param string   $apiMethod
	 * @param GSObject $params
	 * @param string   $dataCenter
	 * @param bool     $useHTTPS
	 *
	 * @return GigyaApiRequest
	 *
	 * @throws \Exception
	 */
	public static function createGSRequestAccessToken($token, $apiMethod, $params, $dataCenter = "us1.gigya.com", $useHTTPS = true) {
		return new GigyaApiRequest($token, null, $apiMethod, $params, $dataCenter, $useHTTPS);
	}

	/**
	 * @param $array
	 *
	 * @return GSObject
	 * @throws GSException
	 * @throws \Exception
	 */
	public static function createGSObjectFromArray($array) {
		if (!is_array($array))
		{
			throw new GSException("Array is expected got " . gettype($array));
		}
		$json = json_encode($array, JSON_UNESCAPED_SLASHES);
		if ($json === false)
		{
			throw new GSException("Error converting array to json see json errno in error code", json_last_error());
		}

		return new GSObject($json);
	}

}
<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit;

use Gigya\GigyaIM\Helper\CmsStarterKit\sdk\GSApiException;
use Gigya\GigyaIM\Helper\CmsStarterKit\sdk\GSFactory;
use Gigya\GigyaIM\Helper\CmsStarterKit\sdk\GSObject;
use Gigya\GigyaIM\Helper\CmsStarterKit\sdk\SigUtils;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUserFactory;

class GigyaApiHelper
{
	private $key;
	private $secret;
	private $apiKey;
	private $dataCenter;
	private $defConfigFilePath;

	const IV_SIZE = 16;

	/**
	 * GigyaApiHelper constructor.
	 *
	 * @param string $apiKey     Gigya API key
	 * @param string $key        Gigya app/user key
	 * @param string $secret     Gigya app/user secret
	 * @param string $dataCenter Gigya data center
	 */
	public function __construct($apiKey, $key, $secret, $dataCenter) {
		$this->defConfigFilePath = DIRECTORY_SEPARATOR . "configuration/DefaultConfiguration.json";
		$defaultConf             = @file_get_contents($this->defConfigFilePath);
		if (!$defaultConf)
		{
			$confArray = array();
		}
		else
		{
			$confArray = json_decode(file_get_contents($this->defConfigFilePath));
		}
		$this->key        = !empty($key) ? $key : $confArray['appKey'];
		$this->secret     = !empty($secret) ? self::decrypt($secret) : self::decrypt($confArray['appSecret']);
		$this->apiKey     = !empty($apiKey) ? $apiKey : $confArray['apiKey'];
		$this->dataCenter = !empty($dataCenter) ? $dataCenter : $confArray['dataCenter'];

	}

	/**
	 * @param string         $method
	 * @param array|GSObject $params
	 *
	 * @return sdk\GSResponse
	 *
	 * @throws \Exception
	 * @throws GSApiException
	 * @throws sdk\GSException
	 */
	public function sendApiCall($method, $params) {
		$req = GSFactory::createGSRequestAppKey($this->apiKey, $this->key, $this->secret, $method,
												GSFactory::createGSObjectFromArray($params), $this->dataCenter);

		return $req->send();
	}

	/**
	 * Validate and get Gigya user
	 *
	 * @param       $uid
	 * @param       $uidSignature
	 * @param       $signatureTimestamp
	 * @param null  $include
	 * @param null  $extraProfileFields
	 * @param array $org_params
	 *
	 * @return bool|user\GigyaUser
	 *
	 * @throws \Exception
	 * @throws GSApiException
	 * @throws sdk\GSException
	 */
	public function validateUid($uid, $uidSignature, $signatureTimestamp, $include = null, $extraProfileFields = null, $org_params = array()) {
		$params                       = $org_params;
		$params['UID']                = $uid;
		$params['UIDSignature']       = $uidSignature;
		$params['signatureTimestamp'] = $signatureTimestamp;
		$res                          = $this->sendApiCall("socialize.exchangeUIDSignature", $params);
		$sig                          = $res->getData()->getString("UIDSignature", null);
		$sigTimestamp                 = $res->getData()->getString("signatureTimestamp", null);
		if (null !== $sig && null !== $sigTimestamp)
		{
			if (SigUtils::validateUserSignature($uid, $sigTimestamp, $this->secret, $sig))
			{
				$user = $this->fetchGigyaAccount($uid, $include, $extraProfileFields, $org_params);

				return $user;
			}
		}

		return false;
	}

	/**
	 * @param string $uid                UID
	 * @param string $include            Fields to include in the response
	 * @param string $extraProfileFields Profile fields to include in the response
	 * @param array  $params             Params
	 *
	 * @return GigyaUser
	 *
	 * @throws \Exception
	 * @throws GSApiException
	 * @throws sdk\GSException
	 */
	public function fetchGigyaAccount($uid, $include = null, $extraProfileFields = null, $params = array()) {
		if (null === $include)
		{
			$include = 'identities-active,identities-all,identities-global,loginIDs,emails,profile,data,password,isLockedOut,'
					   . 'lastLoginLocation,regSource,irank,rba,subscriptions,userInfo,preferences';
		}
		if (null === $extraProfileFields)
		{
			$extraProfileFields = 'languages,address,phones,education,educationLevel,honors,publications,patents,certifications,'
								  . 'professionalHeadline,bio,industry,specialties,work,skills,religion,politicalView,interestedIn,relationshipStatus,'
								  . 'hometown,favorites,followersCount,followingCount,username,name,locale,verified,timezone,likes,samlData';
		}

		$params['UID']                = $uid;
		$params['include']            = $include;
		$params['extraProfileFields'] = $extraProfileFields;

		$res       = $this->sendApiCall('accounts.getAccountInfo', $params);
		$dataArray = $res->getData()->serialize();

		$profileArray = $dataArray['profile'];
		$gigyaUser    = GigyaUserFactory::createGigyaUserFromArray($dataArray);
		$gigyaProfile = GigyaUserFactory::createGigyaProfileFromArray($profileArray);
		$gigyaUser->setProfile($gigyaProfile);

		return $gigyaUser;
	}

	/**
	 * Send all the Gigya data for the user specified by the UID
	 *
	 * Data format example :
	 * Array
	 *    (
	 *        [UID] => 60b1084f2ee846b883e84d6183575f71
	 *        [data] => Array
	 *            (
	 *                [hasChildren] => 1
	 *                [age] => 40
	 *             )
	 *        [isVerified] => 1
	 *        [profile] => Array
	 *            (
	 *                [gender] => u
	 *                [nickname] => Test6
	 *            )
	 *        [subscriptions] => Array
	 *            (
	 *                [demo] => Array
	 *                    (
	 *                        [email] => Array
	 *                            (
	 *                                [isSubscribed] => 1
	 *                                [tags] => Array
	 *                                    (
	 *                                        [0] => test1
	 *                                        [1] => test3
	 *                                    )
	 *                            )
	 *                    )
	 *            )
	 *    )
	 *
	 * @param string $uid  UID
	 * @param array  $data data
	 *
	 * @throws \Exception
	 * @throws \InvalidArgumentException
	 * @throws GSApiException
	 * @throws sdk\GSException
	 */
	public function updateGigyaAccount($uid, $data) {
		if (empty($uid))
		{
			throw new \InvalidArgumentException('UID cannot be empty');
		}

		$paramsArray['UID'] = $uid;
		$paramsArray        = array_merge($paramsArray, $data);

		$jsonObjects = array('data', 'preferences', 'profile', 'subscriptions', 'rba');
		foreach ($jsonObjects as $jsonObject)
		{
			if (isset($paramsArray[$jsonObject]))
			{
				if (empty($paramsArray[$jsonObject]))
					unset($paramsArray[$jsonObject]);
				elseif (is_array($paramsArray[$jsonObject]))
				{
					array_walk_recursive($paramsArray[$jsonObject], function(&$value) {
						$value = strval($value);
					});
					$paramsArray[$jsonObject] = json_encode($paramsArray[$jsonObject]);
				}
			}
		}

		$this->sendApiCall('accounts.setAccountInfo', $paramsArray);
	}

	/**
	 * @throws \Exception
	 * @throws GSApiException
	 * @throws sdk\GSException
	 */
	public function getSiteSchema() {
		$params = GSFactory::createGSObjectFromArray(array("apiKey" => $this->apiKey));
		$this->sendApiCall("accounts.getSchema", $params);
		//        $res    = $this->sendApiCall("accounts.getSchema", $params);
		//TODO: implement
	}

	/**
	 * @param null $apiKey
	 *
	 * @return bool
	 *
	 * @throws \Exception
	 * @throws sdk\GSException
	 */
	public function isRaasEnabled($apiKey = null) {
		if (null === $apiKey)
		{
			$apiKey = $this->apiKey;
		}
		$params = GSFactory::createGSObjectFromArray(array("apiKey" => $apiKey));
		try
		{
			$this->sendApiCall("accounts.getGlobalConfig", $params);

			return true;
		}
		catch (GSApiException $e)
		{
			if ($e->getErrorCode() == 403036)
			{
				return false;
			}
			throwException($e);
		}

		return false;
	}

	public function queryDs($uid, $table, $fields) {


	}

	public function userObjFromArray($user_arr) {
		$obj = GigyaUserFactory::createGigyaUserFromArray($user_arr);

		return $obj;
	}

	/*** Static ***/

	/**
	 * @param string        $str
	 * @param null | string $key
	 *
	 * @return string
	 */
	static public function decrypt($str, $key = null) {
		if (null == $key)
		{
			$key = getenv("KEK");
		}
		if (!empty($key))
		{
			$strDec        = base64_decode($str);
			$iv            = substr($strDec, 0, self::IV_SIZE);
			$text_only     = substr($strDec, self::IV_SIZE);
			$plaintext_dec = openssl_decrypt($text_only, 'AES-256-CBC', $key, 0, $iv);

			return $plaintext_dec;
		}

		return $str;
	}

	/**
	 * @param string        $str
	 * @param null | string $key
	 *
	 * @return string
	 */
	static public function enc($str, $key = null) {
		if (null == $key)
		{
			$key = getenv("KEK");
		}
		$iv    = openssl_random_pseudo_bytes(self::IV_SIZE);
		$crypt = openssl_encrypt($str, 'AES-256-CBC', $key, null, $iv);

		return trim(base64_encode($iv . $crypt));
	}

	/**
	 * Alias of enc()
	 *
	 * @param string        $str
	 * @param null | string $key
	 *
	 * @return string
	 */
	public static function encrypt($str, $key = null) {
		return GigyaApiHelper::enc($str, $key);
	}

	/**
	 * @param null | string $str
	 *
	 * @return mixed
	 */
	static public function genKeyFromString($str = null) {
		if (null == $str)
		{
			$str = openssl_random_pseudo_bytes(32);
		}
		$salt = openssl_random_pseudo_bytes(32);
		$key  = hash_pbkdf2("sha256", $str, $salt, 1000, 32);

		return $key;
	}
}

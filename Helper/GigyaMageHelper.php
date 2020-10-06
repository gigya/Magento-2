<?php
/**
 * Gigya IM Helper
 */
namespace Gigya\GigyaIM\Helper;

use Firebase\JWT\JWT;
use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException;
use Gigya\PHP\GSException;
use Gigya\PHP\SigUtils;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Gigya\GigyaIM\Model\Settings;
use Gigya\GigyaIM\Model\Config;
use Gigya\GigyaIM\Model\SettingsFactory;
use Gigya\GigyaIM\Encryption\Encryptor;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;

class GigyaMageHelper extends AbstractHelper
{
    const MODULE_NAME = 'Gigya_GigyaIM';

    private $extra_profile_fields_config = "https://s3.amazonaws.com/gigya-cms-configs/extraProfileFieldsMap.json";

    private $apiKey;
    private $apiDomain;
	private $authMode;
    private $appKey;
    private $keyFileLocation;
    private $debug;

	private $privateKey;
    private $appSecret;

    /** @var GigyaApiHelper  */
    protected $gigyaApiHelper;
    protected $configSettings;
    protected $_moduleList;
    protected $configModel;
    protected $cookieManager;

    public $logger;

    /** @var  Session */
    protected $session;

    protected $sigUtils;

    /**
     * @var Encryptor
     */
    protected $encryptor;

    const CHARS_PASSWORD_LOWERS = 'abcdefghjkmnpqrstuvwxyz';
    const CHARS_PASSWORD_UPPERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const CHARS_PASSWORD_DIGITS = '23456789';
    const CHARS_PASSWORD_SPECIALS = '!$*-.=?@_';

	/**
	 * GigyaMageHelper constructor.
	 *
	 * @param Context                $context
	 * @param GigyaLogger            $logger
	 * @param ModuleListInterface    $moduleList
	 * @param Config                 $configModel
	 * @param Session                $session
	 * @param CookieManagerInterface $cookieManager
	 * @param SigUtils               $sigUtils
	 * @param Encryptor              $encryptor
	 *
	 * @throws \Exception
	 */
    public function __construct(
        Context $context,
        GigyaLogger $logger,
        ModuleListInterface $moduleList,
        Config $configModel,
        Session $session,
        CookieManagerInterface $cookieManager,
        SigUtils $sigUtils,
        Encryptor $encryptor
    ) {
        parent::__construct($context);

        $this->configSettings = $context->getScopeConfig()->getValue('gigya_section/general', 'website');
        $this->logger = $logger;
        $this->configModel = $configModel;
	    $this->scopeConfig = $context->getScopeConfig();
        $this->_moduleList = $moduleList;
        $this->session = $session;
        $this->cookieManager = $cookieManager;
        $this->sigUtils = $sigUtils;
        $this->encryptor = $encryptor;

        $this->setGigyaSettings();
    }

    /**
     * @return string
     */
	public function getAppSecret()
    {
        return $this->appSecret;
    }

	/**
	 * @return string
	 */
	public function getPrivateKey() {
    	return $this->privateKey ?? '';
	}

    /**
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @param mixed $apiKey
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

	/**
	 * @param string $privateKey
	 */
	public function setPrivateKey($privateKey)
	{
		$this->privateKey = $privateKey;
	}

	/**
	 * @param mixed $appSecret
	 */
	public function setAppSecret($appSecret)
	{
		$this->appSecret = $appSecret;
	}

    /**
     * @return string
     */
	public function getApiDomain()
    {
        return $this->apiDomain;
    }

    /**
     * @param mixed $apiDomain
     */
    public function setApiDomain($apiDomain)
    {
        $this->apiDomain = $apiDomain;
    }

    /**
     * @return string
     */
    public function getAppKey()
    {
        return $this->appKey;
    }

	/**
	 * @return string
	 */
    public function getAuthMode() {
    	return $this->authMode ?? 'user_secret';
	}

    /**
     * @param mixed $appKey
     */
    public function setAppKey($appKey)
    {
        $this->appKey = $appKey;
    }

    /**
     * Return the max number of attempt of automatic Gigya update retry.
     *
     * Configuration in BO.
     *
     * @return int
     */
    public function getMaxRetryCountForGigyaUpdate()
    {
        return (int)$this->scopeConfig->getValue('gigya_advanced/synchro/gigya_update_max_retry');
    }

	/**
	 * Gigya settings are set in Stores->configuration->Gigya Identity management
	 *
	 * @param string $scopeType
	 * @param        $scopeCode
	 * @param        $settings
	 *
	 * @throws \Exception
	 */
	public function setGigyaSettings(
		$scopeType = ScopeInterface::SCOPE_WEBSITE,
		$scopeCode = null,
		$settings = null
	) {
		$savedSettings = $this->configModel->getGigyaGeneralConfig($scopeType, $scopeCode);

		if (is_array($settings) == false) {
			$settings = $savedSettings;
		} else {
			$settings = array_merge($savedSettings, $settings);
		}

		/* Initializes an empty settings array if the settings have not been set */
		$availableSettings = [
			'api_key',
			'app_secret',
			'domain',
			'data_center_host',
			'app_key',
			'key_file_location',
			'enable_gigya',
		];
		$settingsInitial   = array_fill_keys($availableSettings, '');
		$settings          = array_merge($settingsInitial, $settings);
		$keyFileLocation   = empty($settings['key_file_location']) ? null : $settings['key_file_location'];

		$this->encryptor->initEncryptor($scopeType, $scopeCode, $keyFileLocation);

		if ($settings['domain'] == \Gigya\GigyaIM\Model\Config\Source\Domain::OTHER) {
			$this->apiDomain = $settings['data_center_host'];
		} else {
			$this->apiDomain = $settings['domain'];
		}

		$this->apiKey    = $settings['api_key'];
		$this->appKey    = $settings['app_key'];
		$this->authMode  = ($settings['authentication_mode']) ?? 'user_secret';
		if ($this->getAuthMode() === 'user_rsa') {
			$this->setPrivateKey((isset($settings['rsa_private_key_decrypted']) && $settings['rsa_private_key_decrypted'] === true) ?
				$settings['rsa_private_key'] : $this->encryptor->decrypt($settings['rsa_private_key']));
		} else {
			$this->setAppSecret((isset($settings['app_secret_decrypted']) && $settings['app_secret_decrypted'] === true) ?
				$settings['app_secret'] : $this->encryptor->decrypt($settings['app_secret']));
		}
	}

	/**
	 * @return GigyaApiHelper|false
	 */
    public function getGigyaApiHelper()
    {
        if ($this->gigyaApiHelper == null) {
            try {
				$authKey = ($this->authMode == 'user_rsa') ? $this->getPrivateKey() : $this->getAppSecret();
                $this->gigyaApiHelper = new GigyaApiHelper($this->apiKey, $this->appKey, $authKey, $this->apiDomain, $this->authMode);
            } catch (\Exception $e) {
                return false;
            }
        }

        return $this->gigyaApiHelper;
    }

    /**
     * CMS+Gigya environment params to send with Gigya API request
     * @return array CMS+Gigya environment params tro send with Gigya API request
     */
    protected function createEnvironmentParam() {
        // get Magento version
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        $magento_version = $productMetadata->getVersion();

        /* get Gigya and PHP version */
        $gigya_version = $this->_moduleList->getOne(self::MODULE_NAME)['setup_version'];
        $php_version = phpversion();

        $org_params = array();
        $org_params["environment"] = "cms_version:Magento_{$magento_version},gigya_version:Gigya_module_{$gigya_version},php_version:{$php_version}";

        return $org_params;
    }

	/**
	 * @param string $uid
	 * @param string $signature	UIDSignature or ID Token
	 * @param string $signatureTimestamp
	 *
	 * @return bool|\Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser
	 *
	 * @throws GSApiException
	 * @throws \Exception
	 */
    public function validateAndFetchRaasUser($uid, $signature, $signatureTimestamp)
    {
        $org_params = $this->createEnvironmentParam();
        $extra_profile_fields_list = $this->setExtraProfileFields();

        $gigya_api_helper = $this->getGigyaApiHelper();

        if ($this->authMode == 'user_secret') {
			$valid = $gigya_api_helper->validateUid(
				$uid, $signature, $signatureTimestamp, null, $extra_profile_fields_list, $org_params
			);
		} else {
			$valid = $gigya_api_helper->validateJwtAuth($uid, $signature, null, $extra_profile_fields_list, $org_params);
		}

        if (!$valid) {
            $this->logger->debug(__FUNCTION__ .
                ": Raas user validation failed. make sure to check your gigya config values. including encryption key location, and Database gigya settings");
        }

        return $valid;
    }

    /**
     * check if field mapping file exists and if extra fields map file is available.
     *  return list of extra fields to fetch from Gigya account
     * @return string $extra_fields_list
     */
    protected function setExtraProfileFields()
    {
        $extra_profile_fields_list = null;
        // if field mapping module is on, set $config_file_path
        if (is_null($this->_moduleList->getOne(self::MODULE_NAME)['setup_version'])) {
            return $extra_profile_fields_list;
        }
        $config_file_path = $this->configModel->getMappingFilePath();

        // if map fields file exists, read map fields file and build gigya fields array
        if (is_null($config_file_path)) {
            $this->logger->debug(
                "setExtraProfileFields: Mapping fields module is on but mapping fields file path is not defined. 
                Define file path at: Stores:Config:Gigya:Field Mapping"
            );
            return $extra_profile_fields_list;
        }
        if(file_exists($config_file_path)) {
            $mapping_json = file_get_contents($config_file_path);
            if(false === $mapping_json) {
                $err     = error_get_last();
                $this->logger->debug(
                    "setExtraProfileFields: Could not read mapping file at: " . $config_file_path .
                    ". error message: ". $err['message']
                );
                return $extra_profile_fields_list;
            }
        } else {
            $this->logger->debug("setExtraProfileFields: Could not find mapping file at: {$config_file_path}");
            return $extra_profile_fields_list;
        }

        $field_map_array = json_decode($mapping_json, true);
        if(!is_array($field_map_array)) {
            $this->logger->debug(
                "setExtraProfileFields: mapping fields file could not be properly parsed."
            );
            return $extra_profile_fields_list;
        }

        // create one dimension array of gigyaName profile fields
        $profile_fields = array();
        foreach ($field_map_array as $full_field) {
            // if gigyaName contains value with profile, add the profile field to profile_fields
            if(strpos($full_field['gigyaName'], "profile") === 0) {
                $field = explode( ".",$full_field['gigyaName'])[1];
                array_push($profile_fields, $field);
            }
        }
        if (count($profile_fields) === 0) {
            return $extra_profile_fields_list;
        }

        // download and create extra fields map array
        $extra_profile_fields_file = file_get_contents($this->extra_profile_fields_config);
        if(false === $extra_profile_fields_file) {
            $err     = error_get_last();
            $this->logger->debug(
                "setExtraProfileFields: Could not read $extra_profile_fields_file from: "
                . $this->extra_profile_fields_config
                ." .error message: ". $err['message']
            );
            return $extra_profile_fields_list;
        }

        $extra_profile_fields_array = json_decode($extra_profile_fields_file);
        if(!is_array($field_map_array)) {
            $this->logger->debug(
                "setExtraProfileFields: extra profile fields file could not be properly parsed."
            );
            return $extra_profile_fields_list;
        }
        //compare arrays for matching fields to map and build extra profile fields list
        $extra_fields_match = array_intersect($extra_profile_fields_array, $profile_fields);
        if(count($extra_fields_match) > 0) {
            $extra_profile_fields_list = implode(",",$extra_fields_match);
        }

        return $extra_profile_fields_list;
    }

    /**
     * @param GigyaUser $gigya_user_account
	 *
     * @return array $message (validation errors messages)
     */
    public function verifyGigyaRequiredFields($gigya_user_account)
    {
        $message = [];
        $loginId = $gigya_user_account->getGigyaLoginId();
        if (empty($loginId)) {
            $this->logger->debug(__FUNCTION__ . "Gigya user does not have email in [loginIDs][emails] array");
            array_push($message, __('Email not supplied. please make sure that your social account provides an email, or contact our support'));
        }
        $profile = $gigya_user_account->getProfile();
        if (!$profile->getFirstName()) {
            $this->logger->debug(__FUNCTION__ . "Gigya Required field missing - first name. check that your gigya screenset has the correct required fields/complete registration settings.");
            array_push($message, __('Required field missing - first name'));
        }
        if (!$profile->getLastName()) {
            $this->logger->debug(__FUNCTION__ . "Gigya Required field missing - last name. check that your gigya screenset has the correct required fields/complete registration settings.");
            array_push($message, __('Required field missing - last name'));
        }
        return $message;
    }

    public function generatePassword($len = 8) {
        $chars = self::CHARS_PASSWORD_LOWERS
            . self::CHARS_PASSWORD_UPPERS
            . self::CHARS_PASSWORD_DIGITS
            . self::CHARS_PASSWORD_SPECIALS;
        $str = $this->getRandomString($len, $chars);
        return 'Gigya_' . $str;
    }

    /**
     * Taken from magento 1 helper core
     * @param $len
     * @param $chars
     * @return mixed
     */
    private function getRandomString($len, $chars)
    {
        if (empty($chars)) {
            $chars = self::CHARS_PASSWORD_LOWERS . self::CHARS_PASSWORD_UPPERS . self::CHARS_PASSWORD_DIGITS;
        }
        for ($i = 0, $str = '', $lc = strlen($chars)-1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }

	/**
	 * Method updateGigyaAccount
	 *
	 * @param string $uid  UID
	 * @param array  $data data
	 *
	 * @return void
	 *
	 * @throws GSApiException
	 */
    public function updateGigyaAccount($uid, $data = array())
    {
        $this->getGigyaApiHelper()->updateGigyaAccount($uid, $data);
    }

	/**
	 * Given a frontend Gigya response, retrieve all the Gigya's account data.
	 *
	 * @param string $loginData A json string issued from frontend Gigya forms.
	 *
	 * @return false|GigyaUser
	 *
	 * @throws GSException If the Gigya service returned an error.
	 * @throws GSApiException
	 */
    public function getGigyaAccountDataFromLoginData($loginData)
    {
        $gigya_validation_o = json_decode($loginData);
        if (!empty($gigya_validation_o->errorCode)) {
           switch($gigya_validation_o->errorCode)  {
               case GigyaAccountServiceInterface::ERR_CODE_LOGIN_ID_ALREADY_EXISTS:
                   $this->logger->debug("Error while retrieving Gigya account data", [
                       'gigya_data' => $loginData,
                       'customer_entity_id' => ($this->session->isLoggedIn()) ? $this->session->getCustomerId() : 'not logged in'
                   ]);
                   throw new GSException("Email already exists.");
               default:
                   $this->logger->debug("Error while retrieving Gigya account data", [
                       'gigya_data' => $loginData,
                       'customer_entity_id' => ($this->session->isLoggedIn()) ? $this->session->getCustomerId() : 'not logged in'
                   ]);
                   throw new GSException(sprintf("Unable to get Gigya account data : %s / %s", $gigya_validation_o->errorCode, $gigya_validation_o->errorMessage));
           }
        }

        return $this->validateAndFetchRaasUser(
            $gigya_validation_o->UID,
			($this->authMode == 'user_secret') ? $gigya_validation_o->UIDSignature : $gigya_validation_o->idToken,
            $gigya_validation_o->signatureTimestamp
        );
    }

	/**
	 * @param $uid
	 *
	 * @return GigyaUser
	 *
	 * @throws GSApiException
	 */
    public function getGigyaAccountDataFromUid($uid)
    {
        return $this->getGigyaApiHelper()->fetchGigyaAccount($uid);
    }

	/**
	 * @return bool
	 */
	public function isSessionExpirationCookieExpired()
	{
		$APIKey = $this->getApiKey();
		$value = $this->cookieManager->getCookie("gltexp_" . $APIKey);
		if (!$value) {
			return true;
		}

		$value = preg_replace('/^(\d+)_.*$/', '$1', $value);
		if (is_numeric($value)) {
			$value = intval($value);
			return ($value < time());
		}
		return true;
	}

	/**
	 * @param int $secondsToExpiration
	 */
	public function setSessionExpirationCookie($secondsToExpiration = null)
	{
		if ($this->configModel->getSessionMode() == Config::SESSION_MODE_EXTENDED)
		{
			$currentTime = $_SERVER['REQUEST_TIME']; // current Unix time (number of seconds since January 1 1970 00:00:00 GMT)

			$APIKey = $this->getApiKey();
			$tokenCookieName = "glt_" . $APIKey;
			if (isset($_COOKIE[$tokenCookieName])) {
				if (is_null($secondsToExpiration)) {
					$secondsToExpiration = $this->configModel->getSessionExpiration();
				}
				$cookieName = "gltexp_" . $APIKey; // define the cookie name
				$cookieValue = $this->calculateExpCookieValue($secondsToExpiration); // calculate the cookie value
				$cookiePath = "/"; // cookie's path must be base domain

				$expirationTime = strval($currentTime + $secondsToExpiration); // expiration time in Unix time format

				setrawcookie($cookieName, $cookieValue, $expirationTime, $cookiePath);
			}
		}
	}

	public function calculateExpCookieValue($secondsToExpiration = null)
	{
		if (is_null($secondsToExpiration)) {
			$secondsToExpiration = $this->configModel->getSessionExpiration();
		}

		$APIKey = $this->getApiKey();
		$tokenCookieName = "glt_" . $APIKey; /* The name of the token-cookie Gigya stores (Gigya Login Token, or GLT) */
		$tokenCookieValue = trim($_COOKIE[$tokenCookieName]);
		$loginToken = explode("|", $tokenCookieValue)[0]; /* Get the login token from the token-cookie. */
		$applicationKey = $this->getAppKey();

		if ($this->getAuthMode() == 'user_rsa') {
			$privateKey = $this->getPrivateKey();
			return $this->calculateDynamicSessionSignatureJwtSigned($loginToken, $secondsToExpiration, $applicationKey, $privateKey);
		} else {
			$secret = $this->getAppSecret();
			return $this->getDynamicSessionSignatureUserSigned($loginToken, $secondsToExpiration, $applicationKey, $secret);
		}
	}

	protected function getDynamicSessionSignatureUserSigned($glt_cookie, $timeoutInSeconds, $userKey, $secret)
	{
		// cookie format:
		// <expiration time in unix time format>_<User Key>_BASE64(HMACSHA1(secret key, <login token>_<expiration time in unix time format>_<User Key>))
		$expirationTimeUnixMS = (SigUtils::currentTimeMillis() / 1000) + $timeoutInSeconds;
		$expirationTimeUnix = (string)floor($expirationTimeUnixMS);
		$unsignedExpString = $glt_cookie . "_" . $expirationTimeUnix . "_" . $userKey;
		$signedExpString = SigUtils::calcSignature($unsignedExpString, $secret); // sign the base string using the secret key

		return $expirationTimeUnix . "_" . $userKey . "_" . $signedExpString; // define the cookie value
	}

	protected function calculateDynamicSessionSignatureJwtSigned(string $loginToken, int $secondsToExpiration, string $applicationKey, string $privateKey)
	{
		$expirationTimeUnixMS = (SigUtils::currentTimeMillis() / 1000) + $secondsToExpiration;
		$expirationTimeUnix   = (string)floor($expirationTimeUnixMS);

		$payload = [
			'sub' => $loginToken,
			'iat' => time(),
			'exp' => intval($expirationTimeUnix),
			'aud' => 'gltexp',
		];

		return JWT::encode($payload, $privateKey, 'RS256', $applicationKey);
	}

	protected function signBaseString($key, $unsignedExpString)
	{
        $unsignedExpString = utf8_encode($unsignedExpString);
        $rawHmac = hash_hmac("sha1", utf8_encode($unsignedExpString), base64_decode($key), true);
        $signature = base64_encode($rawHmac);
        return $signature;
    }

	/**
	 * @param CustomerInterface $from
	 * @param CustomerInterface $to
	 *
	 * @return $this
	 */
    public function transferAttributes(CustomerInterface $from, CustomerInterface $to)
	{
		$ext = $from->getExtensionAttributes();

		if (!is_null($ext)) {
			$to->setExtensionAttributes($ext);
		}

		foreach (get_class_methods(CustomerInterface::class) as $method) {
			$match = [];

			if (preg_match('/^get(.*)/', $method, $match)
				&& $method != 'getId'
				&& $method != 'getExtensionAttributes'
				&& $method != 'getCustomAttribute'
				&& $method != 'getData'
			) {
				$getter = $method;
				$setter = 'set' . $match[1];
				if (method_exists($to, $setter)) {
					$to->$setter($from->$getter());
				}
			}
		}

		return $this;
	}
}

<?php
/**
 * Gigya IM Helper
 */
namespace Gigya\GigyaIM\Helper;

use Gigya\CmsStarterKit\sdk\GSException;
use Gigya\CmsStarterKit\sdk\SigUtils;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Model\Settings;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Gigya\GigyaIM\Model\Config;
use Gigya\GigyaIM\Model\SettingsFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Gigya\GigyaIM\Logger\Logger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Gigya\CmsStarterKit\GigyaApiHelper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;

class GigyaMageHelper extends AbstractHelper
{
    const MODULE_NAME = 'Gigya_GigyaIM';

    private $extra_profile_fields_config = "https://s3.amazonaws.com/gigya-cms-configs/extraProfileFieldsMap.json";

    private $apiKey;
    private $apiDomain;
    private $appKey;
    private $keyFileLocation;
    private $debug;

    private $appSecret;

    /** @var GigyaApiHelper  */
    protected $gigyaApiHelper;
    protected $configSettings;
    protected $dbSettings;
    protected $_moduleList;
    protected $configModel;

    public $_logger;

    /** @var  Session */
    protected $session;

    /** @var Filesystem  */
    protected $_fileSystem;

    protected $sigUtils;

    const CHARS_PASSWORD_LOWERS = 'abcdefghjkmnpqrstuvwxyz';
    const CHARS_PASSWORD_UPPERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const CHARS_PASSWORD_DIGITS = '23456789';
    const CHARS_PASSWORD_SPECIALS = '!$*-.=?@_';

    public function __construct(
        SettingsFactory $settingsFactory, // virtual class
        Settings $settings,
        Context $context,
        Logger $logger,
        ModuleListInterface $moduleList,
        Config $configModel,
        Session $session,
        Filesystem $fileSystem,
        CookieManagerInterface $cookieManager,
        SigUtils $sigUtils
    ) {
        parent::__construct($context);

        $this->configSettings = $context->getScopeConfig()->getValue('gigya_section/general');
        $this->dbSettings = $settings->load(1);
        $this->_logger = $logger;
        $this->configModel = $configModel;
	    $this->scopeConfig = $context->getScopeConfig();
        $this->_fileSystem = $fileSystem;
        $this->setGigyaSettings();
        $this->setAppSecret();
        $this->_moduleList = $moduleList;
        $this->session = $session;
        $this->cookieManager = $cookieManager;
        $this->sigUtils = $sigUtils;
    }

    /**
     * @return string
     */
    public function getAppSecret()
    {
        return $this->appSecret;
    }

    /**
     * decrypt application secret and set appSecret value
     */
    public function setAppSecret()
    {
        $this->appSecret = $this->decAppSecret();
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
     * @return mixed
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
     * @return mixed
     */
    public function getAppKey()
    {
        return $this->appKey;
    }

    /**
     * @param mixed $appKey
     */
    public function setAppKey($appKey)
    {
        $this->appKey = $appKey;
    }

    /**
     * @return mixed
     */
    public function getKeyFileLocation()
    {
        return $this->keyFileLocation;
    }

    /**
     * @param mixed $keyFileLocation
     */
    public function setKeyFileLocation($keyFileLocation)
    {
        $this->keyFileLocation = $this->_fileSystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath()
            . DIRECTORY_SEPARATOR . $keyFileLocation;
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
        return (int)$this->scopeConfig->getValue('gigya_section/synchro/gigya_update_max_retry');
    }

    /**
     * @return mixed
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * @param mixed $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * Gigya settings are set in Stores->configuration->Gigya Identity management
     */
    private function setGigyaSettings()
    {
        $settings = $this->configModel->getGigyaGeneralConfig();
        $this->apiKey = $settings['api_key'];
        $this->apiDomain = $settings['domain'];
        $this->appKey = $settings['app_key'];
        $this->debug = $settings['debug_mode'];
        $this->keyFileLocation = $this->_fileSystem->getDirectoryRead(DirectoryList::VAR_DIR)->getAbsolutePath()
            . DIRECTORY_SEPARATOR . $settings['key_file_location'];
        $this->debug = $settings['debug_mode'];
    }

    public function getGigyaApiHelper()
    {
        if ($this->gigyaApiHelper == null) {
            $this->gigyaApiHelper = new GigyaApiHelper($this->apiKey, $this->appKey, $this->appSecret, $this->apiDomain);
        }

        return $this->gigyaApiHelper;
    }

    public function userObjFromArr($userArray)
    {
        $obj = $this->getGigyaApiHelper()->userObjFromArray($userArray);
        return $obj;
    }

    /**
     * @return string decrypted app secret
     */
    private function decAppSecret()
    {
        // get encrypted app secret from DB
        $encrypted_secret = $this->dbSettings['app_secret'];
        if (strlen($encrypted_secret) < 5 ) {
            $this->gigyaLog(__FUNCTION__ . " No valid secret key found in DB.");
        }

        $key = $this->getEncKey();
        $dec = GigyaApiHelper::decrypt($encrypted_secret, $key);
        return $dec;
    }

    /**
     * @return string encryption key from file
     */
    private function getEncKey()
    {
        $key = null;
        if ($this->keyFileLocation != '') {
            if (file_exists($this->keyFileLocation)) {
                $key = file_get_contents($this->keyFileLocation);
            } else {
                $this->gigyaLog(__FUNCTION__
                    . ": Could not find key file as defined in Gigya system config : " . $this->keyFileLocation);
            }
        } else {
            $this->gigyaLog(__FUNCTION__
                . ": KEY_PATH is not set in Gigya system config.");
        }
        return trim($key);
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

        // get Gigya version
        $gigya_version = $this->_moduleList->getOne(self::MODULE_NAME)['setup_version'];

        $org_params = array();
        $org_params["environment"] = "cms_version:Magento_{$magento_version},gigya_version:Gigya_module_{$gigya_version}";
        return $org_params;
    }

    /**
     * @param $UID
     * @param $UIDSignature
     * @param $signatureTimestamp
     * @return bool|\Gigya\CmsStarterKit\user\GigyaUser
     */
    public function validateAndFetchRaasUser($UID, $UIDSignature, $signatureTimestamp)
    {
        $org_params = $this->createEnvironmentParam();
        $extra_profile_fields_list = $this->setExtraProfileFields();
        $valid = $this->getGigyaApiHelper()->validateUid(
            $UID, $UIDSignature, $signatureTimestamp, null, $extra_profile_fields_list, $org_params
        );
        if (!$valid) {
            $this->gigyaLog(__FUNCTION__ .
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
            $this->gigyaLog(
                "setExtraProfileFields: Mapping fields module is on but mapping fields file path is not defined. 
                Define file path at: Stores:Config:Gigya:Field Mapping"
            );
            return $extra_profile_fields_list;
        }
        if(file_exists($config_file_path)) {
            $mapping_json = file_get_contents($config_file_path);
            if(false === $mapping_json) {
                $err     = error_get_last();
                $this->gigyaLog(
                    "setExtraProfileFields: Could not read mapping file at: " . $config_file_path .
                    ". error message: ". $err['message']
                );
                return $extra_profile_fields_list;
            }
        } else {
            $this->gigyaLog("setExtraProfileFields: Could not find mapping file at: {$config_file_path}");
            return $extra_profile_fields_list;
        }

        $field_map_array = json_decode($mapping_json, true);
        if(!is_array($field_map_array)) {
            $this->gigyaLog(
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
            $this->gigyaLog(
                "setExtraProfileFields: Could not read $extra_profile_fields_file from: "
                . $this->extra_profile_fields_config
                ." .error message: ". $err['message']
            );
            return $extra_profile_fields_list;
        }

        $extra_profile_fields_array = json_decode($extra_profile_fields_file);
        if(!is_array($field_map_array)) {
            $this->gigyaLog(
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
     * @param $gigya_user_account
     * @return array $message (validation errors messages)
     */
    public function verifyGigyaRequiredFields($gigya_user_account)
    {
        $message = [];
        $loginId = $gigya_user_account->getGigyaLoginId();
        if (empty($loginId)) {
            $this->gigyaLog(__FUNCTION__ . "Gigya user does not have email in [loginIDs][emails] array");
            array_push($message, __('Email not supplied. please make sure that your social account provides an email, or contact our support'));
        }
        $profile = $gigya_user_account->getProfile();
        if (!$profile->getFirstName()) {
            $this->gigyaLog(__FUNCTION__ . "Gigya Required field missing - first name. check that your gigya screenset has the correct required fields/complete registration settings.");
            array_push($message, __('Required field missing - first name'));
        }
        if (!$profile->getLastName()) {
            $this->gigyaLog(__FUNCTION__ . "Gigya Required field missing - last name. check that your gigya screenset has the correct required fields/complete registration settings.");
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
     * @param $length
     * @param $chars
     * @return mixed
     */
    private function getRandomString($len, $chars)
    {
        if (is_null($chars)) {
            $chars = self::CHARS_LOWERS . self::CHARS_UPPERS . self::CHARS_DIGITS;
        }
        for ($i = 0, $str = '', $lc = strlen($chars)-1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
    }

    public function gigyaLog($message) {
        if ($this->debug) {
            $this->_logger->info($message);
        }
    }

    /**
     * @see GigyaApiHelper::updateGigyaAccount()
     *
     * @return void
     */
    public function updateGigyaAccount($uid, $profile = array(), $data = array())
    {
        $this->getGigyaApiHelper()->updateGigyaAccount($uid, $profile, $data);
    }

    /**
     * Given a frontend Gigya response, retrieve all the Gigya's account data.
     *
     * @param string $loginData A json string issued from frontend Gigya forms.
     * @return false|GigyaUser
     * @throws GSException If the Gigya service returned an error.
     */
    public function getGigyaAccountDataFromLoginData($loginData)
    {
        $gigya_validation_o = json_decode($loginData);
        if (!empty($gigya_validation_o->errorCode)) {
           switch($gigya_validation_o->errorCode)  {
               case GigyaAccountServiceInterface::ERR_CODE_LOGIN_ID_ALREADY_EXISTS:
                   $this->_logger->error("Error while retrieving Gigya account data", [
                       'gigya_data' => $loginData,
                       'customer_entity_id' => ($this->session->isLoggedIn()) ? $this->session->getCustomerId() : 'not logged in'
                   ]);
                   throw new GSException("Email already exists.");

               default:
                   $this->_logger->error("Error while retrieving Gigya account data", [
                       'gigya_data' => $loginData,
                       'customer_entity_id' => ($this->session->isLoggedIn()) ? $this->session->getCustomerId() : 'not logged in'
                   ]);
                   throw new GSException(sprintf("Unable to get Gigya account data : %s / %s", $gigya_validation_o->errorCode, $gigya_validation_o->errorMessage));
           }
        }

        $valid_gigya_user = $this->validateAndFetchRaasUser(
            $gigya_validation_o->UID,
            $gigya_validation_o->UIDSignature,
            $gigya_validation_o->signatureTimestamp
        );

        return $valid_gigya_user;
    }

    public function getGigyaAccountDataFromUid($uid)
    {
        return $this->getGigyaApiHelper()->fetchGigyaAccount($uid);
    }

    public function isSessionExpirationCookieExpired()
    {
        $APIKey = $this->getApiKey();
        $value = $this->cookieManager->getCookie("gltexp_" . $APIKey);
        if(!$value)
        {
            return true;
        }
        $value = preg_replace('/^(\d+)_.*$/', '$1', $value);
        if(is_numeric($value))
        {
            $value = intval($value);
            return $value < time();
        }
        return true;
    }

    /*
     * The following features are temporary: they serve to test/analyze the creation of the Session Extension cookie
     * CATODO: clean this section up
     */
    public function setSessionExpirationCookie($secondsToExpiration = null)
    {
        $currentTime = $_SERVER['REQUEST_TIME']; // current Unix time (number of seconds since January 1 1970 00:00:00 GMT)

        $APIKey = $this->getApiKey();
        $tokenCookieName = "glt_" . $APIKey;
        if(isset($_COOKIE[$tokenCookieName]))
        {
            if(is_null($secondsToExpiration))
            {
                $secondsToExpiration = $this->configModel->getSessionExpiration();
            }
            $cookieName = "gltexp_" . $APIKey;  // define the cookie name
            $cookieValue = $this->calculateExpCookieValue($secondsToExpiration);    // calculate the cookie value
            $cookiePath = "/";     // cookie's path must be base domain

            $expirationTime = strval($currentTime + $secondsToExpiration); // expiration time in Unix time format

            setrawcookie($cookieName, $cookieValue, $expirationTime, $cookiePath);
        }
    }

    public function calculateExpCookieValue($secondsToExpiration = null) {
        if(is_null($secondsToExpiration))
        {
            $secondsToExpiration = $this->configModel->getSessionExpiration();
        }

        $APIKey = $this->getApiKey();
        $tokenCookieName = "glt_" . $APIKey;   //  the name of the token-cookie Gigya stores
        $tokenCookieValue = trim($_COOKIE[$tokenCookieName]);
        $loginToken = explode("|", $tokenCookieValue)[0]; // get the login token from the token-cookie.
        $applicationKey = $this->getAppKey();
        $secret = $this->getAppSecret();


        return $this->getDynamicSessionSignatureUserSigned($loginToken, $secondsToExpiration, $applicationKey, $secret);

    }

    protected function getDynamicSessionSignatureUserSigned($glt_cookie, $timeoutInSeconds, $userKey, $secret)
    {
        // cookie format:
        // <expiration time in unix time format>_<User Key>_BASE64(HMACSHA1(secret key, <login token>_<expiration time in unix time format>_<User Key>))
        $expirationTimeUnixMS = (SigUtils::currentTimeMillis() / 1000) + $timeoutInSeconds;
        $expirationTimeUnix = (string)floor($expirationTimeUnixMS);
        $unsignedExpString = $glt_cookie . "_" . $expirationTimeUnix . "_" . $userKey;
        $signedExpString = SigUtils::calcSignature($unsignedExpString, $secret); // sign the base string using the secret key

        $ret = $expirationTimeUnix . "_" . $userKey . "_" . $signedExpString;   // define the cookie value

        return $ret;
    }


    protected function signBaseString($key, $unsignedExpString) {
        $unsignedExpString = utf8_encode($unsignedExpString);
        $rawHmac = hash_hmac("sha1", utf8_encode($unsignedExpString), base64_decode($key), true);
        $signature = base64_encode($rawHmac);
        return $signature;
    }

    public function transferAttributes(
        \Magento\Customer\Api\Data\CustomerInterface $from, \Magento\Customer\Api\Data\CustomerInterface $to)
    {
        $ext = $from->getExtensionAttributes();
        if(!is_null($ext))
        {
            $to->setExtensionAttributes($ext);
        }
        foreach(get_class_methods(\Magento\Customer\Api\Data\CustomerInterface::class) as $method)
        {
            $match = [];
            if(preg_match('/^get(.*)/', $method, $match)
                && $method != 'getId' && $method != 'getExtensionAttributes'
                && $method != 'getCustomAttribute' && $method != 'getData')
            {
                $getter = $method;
                $setter = 'set'.$match[1];
                if(method_exists($to, $setter))
                {
                    $to->$setter($from->$getter());
                }
            }
        }
        return $this;
    }
}

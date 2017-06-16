<?php
/**
 * Gigya IM Helper
 */
namespace Gigya\GigyaIM\Helper;

use Gigya\CmsStarterKit\sdk\GSException;
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
    /** @var GigyaSyncHelper  */
    protected $gigyaSyncHelper;
    protected $configSettings;
    protected $dbSettings;
    protected $_moduleList;
    protected $configModel;

    public $_logger;

    /** @var  Session */
    protected $session;

    /** @var Filesystem  */
    protected $_fileSystem;

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
        GigyaSyncHelper $gigyaSyncHelper,
        Session $session,
        Filesystem $fileSystem
    ) {
        parent::__construct($context);

        $this->configSettings = $context->getScopeConfig()->getValue('gigya_section/general');
        $this->dbSettings = $settings->load(1);
        $this->_logger = $logger;
        $this->configModel = $configModel;
	$this->scopeConfig = $context->getScopeConfig();
	    $this->scopeConfig = $context->getScopeConfig();
        $this->_fileSystem = $fileSystem;
        $this->setGigyaSettings();
        $this->setAppSecret();
        $this->_moduleList = $moduleList;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->session = $session;
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
        return $key;
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

    /**
     * For a given Gigya account data : identify which Magento customer account is to be logged in, and the email that shall be set on this account.
     *
     * The Gigya account furnished will be set on session variable 'gigya_account_data' : get it with Magento\Customer\Model\Session::getGigyaAccountData()
     * The email associated for the Magento account will be set on session variable 'gigya_account_logging_email' : get it with Magento\Customer\Model\Session::getGigyaAccountLoggingEmail()
     *
     * @param GigyaUser $gigyaAccount The data returned by the Gigya's service on customer logging or profile edit.
     * @return CustomerInterface The Magento customer linked with this Gigya account (can be null if no account exists yet)
     * @throws @see GigyaSyncHelper::getMagentoCustomerAndLoggingEmail()
     */
    public function setMagentoLoggingContext($gigyaAccount)
    {
        // This value will be set with the preferred email that should be attached with the Magento customer account, among all the Gigya loginIDs emails
        // We initialize it to null. If it's still null at the end of the algorithm that means that the user can not logged in
        // because all Gigya loginIDs emails are already set to existing Magento customer accounts with a different or null Gigya UID
        $this->session->setGigyaAccountLoggingEmail(null);
        // This will be set with the incoming $gigyaAccount parameter if the customer can be logged in on Magento.
        $this->session->setGigyaAccountData(null);

        $result = $this->gigyaSyncHelper->getMagentoCustomerAndLoggingEmail($gigyaAccount);

        $this->session->setGigyaAccountData($gigyaAccount);
        $this->session->setGigyaAccountLoggingEmail($result['logging_email']);

        return $result['customer'];
    }
}

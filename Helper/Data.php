<?php
namespace Gigya\GigyaIM\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Framework\App\Helper\Context;
use \Gigya\GigyaIM\Logger\Logger;

// check for compile mode location 
include_once $_SERVER["DOCUMENT_ROOT"] . '/app/code/Gigya/GigyaIM/sdk/gigya_config.php';
include_once $_SERVER["DOCUMENT_ROOT"]  . '/app/code/Gigya/GigyaIM/sdk/gigyaCMS.php';

class Data extends AbstractHelper
{
    private $apiKey = API_KEY;
    private $apiDomain = API_DOMAIN;
    private $appKey = APP_KEY;
    private $appSecret;
    private $debug = GIGYA_DEBUG;

    protected $_logger;
    protected $gigyaCMS;
    protected $settingsFactory;

    const CHARS_PASSWORD_LOWERS = 'abcdefghjkmnpqrstuvwxyz';
    const CHARS_PASSWORD_UPPERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const CHARS_PASSWORD_DIGITS = '23456789';
    const CHARS_PASSWORD_SPECIALS = '!$*-.=?@_';

    public function __construct(
        \Gigya\GigyaIM\Model\SettingsFactory $settingsFactory, // virtual class
        Context $context,
        Logger $logger
    )
    {
        parent::__construct($context);
        $this->settingsFactory = $settingsFactory;
        $this->appSecret = $this->_decAppSecret();
        $this->gigyaCMS = new \GigyaCMS($this->apiKey, NULL, $this->apiDomain, $this->appSecret, $this->appKey, TRUE, $this->debug, $logger);
        $this->_logger = $logger;

        
    }

    /**
     * @return string decrypted app secret
     */
    private function _decAppSecret() {
        // get encrypted app secret from DB
        $settings = $this->settingsFactory->create();
        $settings = $settings->load(1);
        $encrypted_secret = $settings->getData('app_secret');
        if (strlen($encrypted_secret) < 5 ) {
            $this->_logger->info(__FUNCTION__ . " No valid secret key found in DB.");
        }

        // get the key if it is saved in external file
        $key = null;
        if (KEY_SAVE_TYPE == "file") {
            $key = $this->getEncKey();
        }
        
        $dec = \GigyaCMS::decrypt($encrypted_secret, $key);
        return $dec;
    }

    /**
     * @return string encryption key from file
     */
    private function getEncKey() {
        $key = null;
        if (defined("KEY_PATH")) {
            if (file_exists(KEY_PATH)) {
                $key = file_get_contents(KEY_PATH);
            } else {
                $this->_logger->info(__FUNCTION__ . ": Could not find key file as defined in Gigya config file : " . KEY_PATH);
            }
        } else {
            $this->_logger->info(__FUNCTION__ . ": KEY_SAVE_TYPE is set to env, but KEY_PATH is not defined in Gigya config file.");
        }
        return $key;
    }
    
    /**
     * @param $gigya_object
     * @return bool
     */
    public function _validateRaasUser($gigya_object) {
        $params = array(
            'UID' => $gigya_object->UID,
            'UIDSignature' => $gigya_object->UIDSignature,
            'signatureTimestamp' => $gigya_object->signatureTimestamp,
        );
        $valid = $this->gigyaCMS->validateUserSignature($params);
        if (!$valid) {
            $this->_logger->info(__FUNCTION__ . ": Raas user validation failed. make sure to check your gigya_config values. including encryption key location, and Database gigya settings");
        }
        return $valid;
    }

    public function _getAccount($uid) {
        $account_info = $this->gigyaCMS->getAccount($uid);
        return $account_info;
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
}
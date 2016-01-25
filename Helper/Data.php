<?php
namespace Gigya\GigyaM2\Helper;

// check for compile mode location
include_once __DIR__ . '/../sdk/GSSDK.php';
include_once __DIR__ . '/../sdk/gigyaCMS.php';

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    private $apiKey = "3_nJILE6pHcAcV_PzORmiO_Y1PYCxRz1ViQySoc_PP78KzgCSrDyvcWrnNeXeO3g9A";
    private $apiDomain = "us1.gigya.com";
    private $appSecret = "y9nP17GRyigy2oKZAq0LwWbolvZJA+QR";
    private $appKey = "ANxj7nGHee98";
    private $debug = FALSE;

    const CHARS_PASSWORD_LOWERS = 'abcdefghjkmnpqrstuvwxyz';
    const CHARS_PASSWORD_UPPERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const CHARS_PASSWORD_DIGITS = '23456789';
    const CHARS_PASSWORD_SPECIALS = '!$*-.=?@_';

    public function __construct()
    {
        $this->utils = new \GigyaCMS($this->apiKey, NULL, $this->apiDomain, $this->appSecret, $this->appKey, TRUE, $this->debug);
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
        $valid = $this->utils->validateUserSignature($params);
        return $valid;
    }

    public function _getAccount($uid) {
        $account_info = $this->utils->getAccount($uid);
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
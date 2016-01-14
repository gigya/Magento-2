<?php
namespace Gigya\GigyaM2\Helper;

// check for compile mode location
include_once __DIR__ . '/../sdk/GSSDK.php';
include_once __DIR__ . '/../sdk/gigyaCMS.php';

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    public function __construct()
    {
        // retrieve dynamically / add comments.
        $this->apiKey = "3_nJILE6pHcAcV_PzORmiO_Y1PYCxRz1ViQySoc_PP78KzgCSrDyvcWrnNeXeO3g9A";
        $this->apiSecret = "/oXDN5CLlWsSFwW/nv9CITVssMEPegOPPmS+PjfZMcY=";
        $this->apiDomain = "us1.gigya.com";
        //
        $this->userSecret = "";
        $this->userKey = "";
        $this->use_user_key = "";
        $this->debug = "";

        $this->utils = new \GigyaCMS($this->apiKey, $this->apiSecret, $this->apiDomain, $this->userSecret, $this->userKey, $this->use_user_key, $this->debug);
    }

    public function _validateRaasUser($gigya_object) {
        // security mode check here: is user secret or app secret.
        // test should be automatic - if a secret key exists then use it. else use user key.
        $valid = false;
        $uid = $gigya_object->UID;
        $timestamp = $gigya_object->signatureTimestamp;
        $signature = $gigya_object->UIDSignature;
        $valid = \SigUtils::validateUserSignature($uid, $timestamp, $this->apiSecret, $signature);
        return $valid;
    }

    public function _getAccount($uid) {
        $account_info = $this->utils->getAccount($uid);
        // add mage1 step?
        return $account_info;
    }

}
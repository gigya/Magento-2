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
        $this->apiDomain = "us1.gigya.com";
        // Set to true to use application secret (default) or false to use client (user) secret:
        $this->use_app_key= TRUE;
        // application key & secret:
        $this->appSecret = "y9nP17GRyigy2oKZAq0LwWbolvZJA+QR";
        $this->appKey = "ANxj7nGHee98";
        // or main client secret:
        $this->apiSecret = "/oXDN5CLlWsSFwW/nv9CITVssMEPegOPPmS+PjfZMcY=";

        $this->debug = FALSE; // default to false

        $this->utils = new \GigyaCMS($this->apiKey, $this->apiSecret, $this->apiDomain, $this->appSecret, $this->appKey, $this->use_app_key, $this->debug);
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

}
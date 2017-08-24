<?php

namespace Gigya\GigyaIM\Helper\Automatic;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\RequestInterface;

class Login extends AbstractHelper
{
    protected $cookieManager;
    protected $host;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager)
    {
        parent::__construct($context);
        $this->cookieManager = $cookieManager;
        $this->host = $context->getHttpHeader()->getHttpHost();
    }

    public function validateAutoLoginParameters(RequestInterface $request)
    {
        $domain = preg_replace('/^([^:]+)((\:\d+)?)$/', '$1', $this->host);
        $guid = $request->getParam('guid');
        $salt = '1234';

        return $request->getParam('key') == base64_encode($domain.$guid.$salt);
    }
}
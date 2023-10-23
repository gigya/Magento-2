<?php

namespace Gigya\GigyaIM\Helper\Automatic;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;

class Login extends AbstractHelper
{
    protected CookieManagerInterface $cookieManager;
    protected string $host;

    public function __construct(
        Context $context,
        CookieManagerInterface $cookieManager
    ) {
        parent::__construct($context);
        $this->cookieManager = $cookieManager;
        $this->host = $context->getHttpHeader()->getHttpHost();
    }

    public function validateAutoLoginParameters(RequestInterface $request): bool
    {
        $domain = preg_replace('/^([^:]+)((\:\d+)?)$/', '$1', $this->host);
        $guid = $request->getParam('guid');
        $salt = '1234';

        return $request->getParam('key') == base64_encode($domain.$guid.$salt);
    }
}

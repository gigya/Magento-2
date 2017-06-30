<?php

namespace Gigya\GigyaIM\Observer\Session;

use Gigya\CmsStarterKit\sdk\SigUtils;
use Gigya\GigyaIM\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ScopeInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagement;
use Magento\Store\Model\StoreManager;

class Extend implements ObserverInterface
{

    /**
     * @var Config
     */
    protected $configModel;

    /**
     * @var \Gigya\GigyaIM\Helper\GigyaMageHelper
     */
    protected $gigyaMageHelper;

    /**
     * @var \Magento\Framework\Stdlib\CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * @var StoreManager
     */
    protected $storeManager;

    public function __construct(
        Config $configModel,
        \Gigya\GigyaIM\Helper\GigyaMageHelper $gigyaMageHelper,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Gigya\GigyaIM\Model\Session $sessionModel,
        UrlInterface $urlInterface,
        StoreManager $storeManager
    )
    {
        $this->configModel = $configModel;
        $this->gigyaMageHelper = $gigyaMageHelper;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->sessionModel = $sessionModel;
        $this->urlInterface = $urlInterface;
        $this->storeManager = $storeManager;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /* @var $request \Magento\Framework\App\RequestInterface */
        $request = $observer->getEvent()->getRequest();
        if($request->isAjax())
        {
            if($this->configModel->getSessionMode() == Config::SESSION_MODE_EXTENDED)
            {
                $apiKey = $this->gigyaMageHelper->getApiKey();

                $expiration = $this->configModel->getSessionExpiration();

                foreach(['PHPSESSID', 'store', 'private_content_version'] as $cookieName)
                {
                    $existingValue = $this->cookieManager->getCookie($cookieName);
                    if(!is_null($existingValue))
                    {
                        $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
                        $publicCookieMetadata
                            ->setDuration($expiration)
                            ->setPath($this->storeManager->getStore()->getStorePath());

                        if($cookieName == 'PHPSESSID')
                        {
                            $domain = preg_replace('/^https?\:\/\/([^:]+)(\:[\d\:\/]*)$/', '$1', $this->urlInterface->getBaseUrl());
                            $publicCookieMetadata->setDomain('.'.$domain);
                        }

                        $this->cookieManager->setPublicCookie(
                            $cookieName,$existingValue, $publicCookieMetadata
                        );
                    }
                }

                $cookieLoginToken = explode("|", trim($this->cookieManager->getCookie('glt_'.$apiKey)))[0];
                $sessionLoginToken = $this->sessionModel->getLoginToken();

                $loginToken = false;

                if($sessionLoginToken)
                {
                    $loginToken = $sessionLoginToken;
                }
                else
                {
                    if($cookieLoginToken)
                    {
                        $this->sessionModel->setLoginToken($cookieLoginToken);
                        $loginToken = $cookieLoginToken;
                    }
                }

                if($loginToken)
                {
                    /*
                    $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
                    $publicCookieMetadata
                        ->setDuration($expiration)
                        ->setPath('/');
                    */
                    $this->gigyaMageHelper->setSessionExpirationCookie($expiration);

                }
            }
            /*
            else
            {
                // The cookie only arrives once
                $apiKey = $this->gigyaMageHelper->getApiKey();

                $expiration = $this->configModel->getSessionExpiration();

                $cookieLoginToken = explode("|", trim($this->cookieManager->getCookie('glt_'.$apiKey)))[0];

                if($cookieLoginToken)
                {
                    $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
                    $publicCookieMetadata
                        ->setDuration($expiration)
                        ->setPath('/');
                    $this->cookieManager->setPublicCookie(
                        'gltexp_'.$apiKey,
                        $this->getDynamicSessionSignature($cookieLoginToken,
                            $expiration, $this->gigyaMageHelper->getAppSecret()
                        ), $publicCookieMetadata
                    );
                }
            }
            */
        }
    }

    public function getDynamicSessionSignature($glt_cookie, $timeoutInSeconds, $secret)
    {
        //<Expiration Time in Unix Time Format> + '_'  + <Application Key> + '_' + BASE64(HMACSHA1(<Your Secret Key>, <Login Token> + '_' + <Expiration Time in Unix Time Format> + '_'  + <Application Key>))

        $expirationTime = time() + $timeoutInSeconds;
        $applicationKey = $this->gigyaMageHelper->getAppKey();
        $loginToken = $glt_cookie;

        return $expirationTime . '_' . $applicationKey . '_' .
            base64_encode(hash_hmac('sha1', $loginToken.'_'.$expirationTime.'_'.$applicationKey , $secret));
    }
}
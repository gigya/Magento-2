<?php

namespace Gigya\GigyaIM\Model\Session;

use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Logger\Logger;
use Gigya\GigyaIM\Model\Session;
use Gigya\GigyaIM\Model\Config;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManager;

class Extend
{

    /**
     * @var Config
     */
    protected $configModel;

    /**
     * @var GigyaMageHelper
     */
    protected $gigyaMageHelper;

    /**
     * @var CookieManagerInterface
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

    protected $cookieMetadataFactory;
    protected $sessionModel;
    protected $logger;

    public function __construct(
        Config $configModel,
        GigyaMageHelper $gigyaMageHelper,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        Session $sessionModel,
        UrlInterface $urlInterface,
        StoreManager $storeManager,
        Logger $logger
    ) {
        $this->configModel = $configModel;
        $this->gigyaMageHelper = $gigyaMageHelper;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->sessionModel = $sessionModel;
        $this->urlInterface = $urlInterface;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @param bool $checkCookieValidity
     *
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function extendSession($checkCookieValidity = true)
    {
        if ($this->configModel->getSessionMode() == Config::SESSION_MODE_EXTENDED) {
            if ((!$this->gigyaMageHelper->isSessionExpirationCookieExpired()) || (!$checkCookieValidity)) {
                $expiration = $this->configModel->getSessionExpiration();

                foreach (['PHPSESSID', 'store', 'private_content_version'] as $cookieName) {
                    $existingValue = $this->cookieManager->getCookie($cookieName);
                    if (!is_null($existingValue)) {
                        $path = preg_replace('/\/index\.php\//', '/', $this->storeManager->getStore()->getStorePath());

                        $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
                        $publicCookieMetadata
                            ->setDuration($expiration)
                            ->setPath($path);

                        if ($cookieName == 'PHPSESSID') {
                            $sessionPath = $this->configModel->getMagentoCookiePath();
                            if (!$sessionPath) {
                                $sessionPath = '/';
                            }
                            $domain = preg_replace(
                                '/^https?\:\/\/([^:\/]+)(\:[\d]+)?\/.*$/',
                                '$1',
                                $this->urlInterface->getBaseUrl()
                            );
                            $publicCookieMetadata->setPath($sessionPath);
                            $publicCookieMetadata->setDomain('.' . $domain);
                        }

                        $this->cookieManager->setPublicCookie($cookieName, $existingValue, $publicCookieMetadata);
                    }
                }

                $this->setupSessionCookie();
            }
        }
    }

    public function setupSessionCookie($type = 'session')
    {
        $apiKey = $this->gigyaMageHelper->getApiKey();

        $expiration = $this->configModel->getSessionExpiration();

        $cookieLoginToken = explode("|", trim($this->cookieManager->getCookie('glt_' . $apiKey)))[0];
        $sessionLoginToken = $this->sessionModel->getLoginToken();

        $loginToken = false;

        if ($sessionLoginToken) {
            $loginToken = $sessionLoginToken;
        } else {
            if ($cookieLoginToken) {
                $this->sessionModel->setLoginToken($cookieLoginToken);
                $loginToken = $cookieLoginToken;
            }
        }

        if ($loginToken) {
            $this->gigyaMageHelper->setSessionExpirationCookie($expiration);
        }
    }

    public function getDynamicSessionSignature($glt_cookie, $timeoutInSeconds, $secret)
    {
        //<Expiration Time in Unix Time Format> + '_'  + <Application Key> + '_' + BASE64(HMACSHA1(<Your Secret Key>, <Login Token> + '_' + <Expiration Time in Unix Time Format> + '_'  + <Application Key>))

        $expirationTime = time() + $timeoutInSeconds;
        $applicationKey = $this->gigyaMageHelper->getAppKey();
        $loginToken = $glt_cookie;

        return $expirationTime . '_' . $applicationKey . '_' .
        base64_encode(hash_hmac('sha1', $loginToken.'_'.$expirationTime.'_'.$applicationKey, $secret));
    }
}

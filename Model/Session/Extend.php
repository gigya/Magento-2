<?php

namespace Gigya\GigyaIM\Model\Session;

use Gigya\PHP\SigUtils;
use Gigya\GigyaIM\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ScopeInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagement;
use Magento\Store\Model\StoreManager;

class Extend
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

    protected $cookieMetadataFactory;
    protected $sessionModel;
    protected $logger;

    public function __construct(
        Config $configModel,
        \Gigya\GigyaIM\Helper\GigyaMageHelper $gigyaMageHelper,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Gigya\GigyaIM\Model\Session $sessionModel,
        UrlInterface $urlInterface,
        StoreManager $storeManager,
        \Gigya\GigyaIM\Logger\Logger $logger
    )
    {
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
	 * @throws \Magento\Framework\Exception\InputException
	 * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
	 * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
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
							$domain = preg_replace('/^https?\:\/\/([^:\/]+)(\:[\d]+)?\/.*$/', '$1',
								$this->urlInterface->getBaseUrl());
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
        base64_encode(hash_hmac('sha1', $loginToken.'_'.$expirationTime.'_'.$applicationKey , $secret));
    }
}

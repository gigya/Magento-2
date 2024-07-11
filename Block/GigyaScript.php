<?php
/*
 * Manage adding Gigya script with API key and global variables
 * Defined in view/frontend/layout/default.xml
 */

namespace Gigya\GigyaIM\Block;

use Gigya\GigyaIM\Helper\GigyaScriptHelper;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\Template;
use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Framework\Url\EncoderInterface;
use Magento\Customer\Model\Url;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Class GigyaScript
 *
 * @package Gigya\GigyaIM\Block
 * @api
 */
class GigyaScript extends Template
{
    /**
     * @var Session
     */
    protected Session $_customerSession;

    /**
     * @var Url
     */
    protected Url $_customerUrl;

    /**
     * @var GigyaLogger
     */
    protected $_logger;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var GigyaConfig
     */
    protected GigyaConfig $configModel;

    /**
     * @var EncoderInterface
     */
    protected EncoderInterface $urlEncoder;

    protected GigyaScriptHelper $scriptHelper;
    private StoreInterface $store;

    /**
     * GigyaScript constructor.
     * @param Context $context
     * @param Session $customerSession
     * @param Url $customerUrl
     * @param GigyaConfig $configModel
     * @param GigyaLogger $logger
     * @param EncoderInterface $urlEncoder
     * @param GigyaScriptHelper $scriptHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        Url $customerUrl,
        GigyaLogger $logger,
        GigyaConfig $configModel,
        EncoderInterface $urlEncoder,
        GigyaScriptHelper $scriptHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_isScopePrivate = false;
        $this->_customerSession = $customerSession;
        $this->_customerUrl = $customerUrl;
        $this->_logger = $logger;
        $this->configModel = $configModel;
        $this->scopeConfig = $context->getScopeConfig();
        $this->urlEncoder = $urlEncoder;
        $this->scriptHelper = $scriptHelper;
    }

    /**
     * @return Template
     */
    protected function _prepareLayout(): Template
    {
        return parent::_prepareLayout();
    }

    public function isGigyaEnabled(): int
    {
        return $this->configModel->isGigyaEnabled();
    }

    public function getScriptsHelper(): GigyaScriptHelper
    {
        return $this->scriptHelper;
    }

    /**
     * Set the frontend user session/remember lifetime according to the configured session/remember mode.
     *
     * @return int : Magento Customer session/remember expiration
     * @see GigyaConfig for session management modes.
     *
     */
    public function getUserSessionLifetime($type = null): int
    {
        $result = null;

        $mode = $this->configModel->getSessionMode($type);

        switch ($mode) {
            case GigyaConfig::SESSION_MODE_FIXED:
                $result = $this->configModel->getSessionExpiration($type);
                break;

            case GigyaConfig::SESSION_MODE_EXTENDED:
                $result = -1;
                break;

            case GigyaConfig::SESSION_MODE_BROWSER_INSTANCE:
                $result = 0;
                break;

            case GigyaConfig::SESSION_MODE_ENDLESS:
                $result = -2;
                break;
        }

        return $result;
    }

    /**
     * @return String Gigya API key set in default.xml
     */
    public function getGigyaApiKey(): string
    {
        return $this->scopeConfig->getValue("gigya_section/general/api_key", "website");
    }

    public function getBaseUrl(): string
    {
        return $this->getUrl('/', ['_secure' => $this->getRequest()->isSecure()]);
    }

    /**
     * Retrieve form posting url
     *
     * @return string
     */
    public function getPostActionUrl(): string
    {
        // If there is no referer defined, defines page itself as a referer
        // This is important in case the customer get to store already logged in from another site
        $referer = $this->getRequest()->getParam(Url::REFERER_QUERY_PARAM_NAME);
        if (empty($referer)) {
            $referer = $this->_urlBuilder->getCurrentUrl();
            $referer = $this->urlEncoder->encode($referer);
            $this->getRequest()->setParam(Url::REFERER_QUERY_PARAM_NAME, $referer);
        }

        return $this->_customerUrl->getLoginPostUrl();
    }

    /**
     * Retrieve URL used for checking the login state
     * @return int|string
     */
    public function getMagentoLoginStateUrl(): int|string
    {
        return (string)$this->getUrl('gigya_raas/raas/state');
    }

    /**
     * @return string
     */
    public function getLogoutUrl(): string
    {
        return (string)$this->getUrl('customer/account/logout');
    }

    /**
     * @return string
     */
    public function getLoginUrl(): string
    {
        return (string)$this->getUrl('gigya_raas/raas_automatic/login');
    }

    /**
     * check language mode in gigya config (mode:auto/en/es..., default:en/other)
     * if auto is selected:
     *   check local language
     *   check if local language is supported by gigya
     *   set language (local/default/en)
     * else set selected language
     */
    public function getLanguage(): mixed
    {
        $lang = $this->scopeConfig->getValue("gigya_section/general/language", "website");
        if ($lang == "auto") {
            $lang = $this->checkLocalLang();
        }

        if (!array_key_exists($lang, $this->gigyaSupportedLanguages())) {
            // log: "local language - $local_lang is not supported by gigya, reverting to default lang"
            $lang = $this->scopeConfig->getValue("gigya_section/general/language_fallback", "website");
        }

        return $lang;
    }

    /**
     * @return string
     */
    protected function checkLocalLang(): string
    {
        /** @var ObjectManagerInterface $om */
        $om = ObjectManager::getInstance();
        /** @var Resolver $resolver */
        $resolver = $om->get('Magento\Framework\Locale\Resolver');
        $local_lang = $resolver->getLocale();

        return substr($local_lang, 0, 2);
    }

    /**
     * Associative array of Gigya supported languages
     */
    protected function gigyaSupportedLanguages(): array
    {
        return [
            "en" => "English", "ar" => "Arabic", "br" => "Bulgarian", "ca" => "Catalan", "hr" => "Croatian",
            "cs" => "Czech", "da" => "Danish", "nl" => "Dutch", "fi" => "Finnish", "fr" => "French", "de" => "German",
            "el" => "Greek", "he" => "Hebrew", "hu" => "Hungarian", "id" => "Indonesian (Bahasa)", "it" => "Italian",
            "ja" => "Japanese", "ko" => "Korean", "ms" => "Malay", "no" => "Norwegian", "fa" => "Persian (Farsi)",
            "pl" => "Polish", "pt" => "Portuguese", "ro" => "Romanian", "ru" => "Russian", "sr" => "Serbian (Cyrillic)",
            "sk" => "Slovak", "sl" => "Slovenian", "es" => "Spanish", "sv" => "Swedish", "tl" => "Tagalog", "th" => "Thai",
            "tr" => "Turkish", "uk" => "Ukrainian", "vi" => "Vietnamese", "zh-cn" => "Chinese (Mandarin)",
            "zh-hk" => "Chinese (Hong Kong)", "zh-tw" => "Chinese (Taiwan)", "nl-inf" => "Dutch Informal",
            "fr-inf" => "French Informal", "de-inf" => "German Informal",
            "pt-br" => "Portuguese (Brazil)", "es-inf" => "Spanish Informal", "es-mx" => "Spanish (Lat-Am)"
        ];
    }
}

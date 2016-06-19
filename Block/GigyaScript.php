<?php
/*
 * Manage adding Gigya script with API key and global variables
 * Defined in view/frontend/layout/default.xml
 */

namespace Gigya\GigyaIM\Block;
use Magento\Framework\View\Element\Template;

class GigyaScript extends Template
{
    /**
     * @var int
     */
    private $_username = -1;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Customer\Model\Url
     */
    protected $_customerUrl;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Customer\Model\Url $customerUrl
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Model\Url $customerUrl,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_isScopePrivate = false;
        $this->_customerUrl = $customerUrl;
        $this->_customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    /**
     * @return int : Magento Customer session expiration
     */
    public function getUserSessionLifetime() {
        return $this->_customerSession->getCookieLifetime();
    }

    /**
     * @return String Gigya API key set in default.xml
     */
    public function getGigyaApiKey() {
        $api = $this->scopeConfig->getValue("gigya_section/general/api_key");
        return $api;
    }

    public function getBaseUrl()
    {
        return $this->getUrl('/', ['_secure' => $this->getRequest()->isSecure()]);
    }

    /**
     * Retrieve form posting url
     *
     * @return string
     */
    public function getPostActionUrl()
    {
        return $this->_customerUrl->getLoginPostUrl();
    }

    /**
     * Check if user is logged in
     * @return int
     */
    public function getMagentoUserLogin() {
        $logged_in = $this->_customerSession->isLoggedIn();
        if ($logged_in) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * check language mode in gigya config (mode:auto/en/es..., default:en/other)
     * if auto is selected:
     *   check local language
     *   check if local language is supported by gigya
     *   set language (local/default/en)
     * else set selected language
     */
    public function getLanguage() {
        $lang = $this->scopeConfig->getValue("gigya_section/general/language");
        if ($lang == "auto") {
            $lang = $this->checkLocalLang();
        }
        if (!array_key_exists($lang, $this->gigyaSupportedLanguages())) {
            // log: "local language - $local_lang is not supported by gigya, reverting to default lang"
            $lang = $this->scopeConfig->getValue("gigya_section/general/language_fallback");
        }
        return $lang;
    }

    protected function checkLocalLang() {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\Locale\Resolver $resolver */
        $resolver = $om->get('Magento\Framework\Locale\Resolver');
        $local_lang = $resolver->getLocale();
        return $local_lang;
    }

    /**
     * associative array of gigya supported languages
     */
    protected function gigyaSupportedLanguages() {
        return array(
            "en_US" => "English","ar" => "Arabic","br" => "Bulgarian","ca" => "Catalan","hr" => "Croatian",
            "cs" => "Czech","da" => "Danish","nl" => "Dutch","fi" => "Finnish","fr" => "French","de" => "German",
            "el" => "Greek","he" => "Hebrew","hu" => "Hungarian","id" => "Indonesian (Bahasa)","it" => "Italian",
            "ja" => "Japanese","ko" => "Korean","ms" => "Malay","no" => "Norwegian","fa" => "Persian (Farsi)",
            "pl" => "Polish","pt" => "Portuguese","ro" => "Romanian","ru" => "Russian","sr" => "Serbian (Cyrillic)",
            "sk" => "Slovak","sl" => "Slovenian","es" => "Spanish","sv" => "Swedish","tl" => "Tagalog","th" => "Thai",
            "tr" => "Turkish","uk" => "Ukrainian","vi" => "Vietnamese","zh-cn" => "Chinese (Mandarin)",
            "zh-hk" => "Chinese (Hong Kong)", "zh-tw" => "Chinese (Taiwan)","nl-inf" => "Dutch Informal",
            "fr-inf" => "French Informal", "de-inf" => "German Informal",
            "pt-br" => "Portuguese (Brazil)","es-inf" => "Spanish Informal", "es-mx" => "Spanish (Lat-Am)"
        );
    }

}
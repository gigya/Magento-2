<?php
/*
 * Manage adding Gigya script with API key and global variables
 * Defined in view/frontend/layout/default.xml
 */

namespace Gigya\GigyaIM\Block;

use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;
use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Framework\Url\EncoderInterface;
use Magento\Customer\Model\Url;
use Magento\Framework\View\Element\Template\Context;

class GigyaScript extends Template
{
    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Customer\Model\Url
     */
    protected $_customerUrl;

	/**
	 * @var GigyaLogger
	 */
    protected $_logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Gigya\GigyaIM\Model\Config
     */
    protected $configModel;

    /**
     * @var EncoderInterface
     */
    protected $urlEncoder;

	/**
	 * GigyaScript constructor.
	 * @param Context $context
	 * @param Session $customerSession
	 * @param Url $customerUrl
	 * @param GigyaConfig $configModel
	 * @param GigyaLogger $logger
	 * @param EncoderInterface $urlEncoder
	 * @param array $data
	 */
    public function __construct(
        Context $context,
        Session $customerSession,
        Url $customerUrl,
	    GigyaConfig $configModel,
        GigyaLogger $logger,
        EncoderInterface $urlEncoder,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_isScopePrivate = false;
        $this->_customerUrl = $customerUrl;
        $this->_customerSession = $customerSession;
        $this->_logger = $logger;
        $this->scopeConfig = $context->getScopeConfig();
        $this->configModel = $configModel;
        $this->urlEncoder = $urlEncoder;
    }

    /**
     * @return Template
     */
    protected function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    public function isGigyaEnabled()
    {
        return $this->configModel->isGigyaEnabled();
    }

    /**
     * Set the frontend user session/remember lifetime according to the configured session/remember mode.
     *
     * @see \Gigya\GigyaIM\Model\Config for session management modes.
     *
     * @return int : Magento Customer session/remember expiration
     */
    public function getUserSessionLifetime($type = null)
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
    public function getGigyaApiKey()
    {
        $api = $this->scopeConfig->getValue("gigya_section/general/api_key", "website");
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
	 * @return int
	 */
	public function getMagentoLoginStateUrl()
	{
		return $this->getUrl('gigya_raas/raas/state');
	}

	public function getLogoutUrl()
	{
		return $this->getUrl('customer/account/logout');
	}

	public function getLoginUrl()
	{
		return $this->getUrl('gigya_raas/raas_automatic/login');
	}

	/**
	 * check language mode in gigya config (mode:auto/en/es..., default:en/other)
	 * if auto is selected:
	 *   check local language
	 *   check if local language is supported by gigya
	 *   set language (local/default/en)
	 * else set selected language
	 */
	public function getLanguage()
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

    protected function checkLocalLang()
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\Locale\Resolver $resolver */
        $resolver = $om->get('Magento\Framework\Locale\Resolver');
        $local_lang = $resolver->getLocale();
        return substr($local_lang, 0, 2);
    }

	/**
	 * Associative array of Gigya supported languages
	 */
	protected function gigyaSupportedLanguages()
	{
		return array(
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
		);
	}
}

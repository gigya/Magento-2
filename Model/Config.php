<?php

namespace Gigya\GigyaIM\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Gigya\GigyaIM\Logger\Logger;

/**
 * Class Config
 *
 * For session mode (constants SESSION_MODE_XXX)
 * cf. https://developers.gigya.com/display/GD/Security+Guidelines#SecurityGuidelines-DefiningaSessionExpirationCookie
 *
 * @package Gigya\GigyaIM\Model
 */
class Config
{
    // Session duration will be fixed by config
    const SESSION_MODE_FIXED = 0;
    // Session duration is 60 secs; automatically renewed on any user action
    const SESSION_MODE_EXTENDED = 1;
    // Session close when browser close
    const SESSION_MODE_BROWSER_INSTANCE = 2;
    // Session never close even when browser close
    const SESSION_MODE_ENDLESS = 3;

    const XML_PATH_SESSION_MODE = 'gigya_session/session/mode';
    const XML_PATH_SESSION_EXPIRATION = 'gigya_session/session/expiration';

    const XML_PATH_REMEMBER_MODE = 'gigya_session/remember/mode';
    const XML_PATH_REMEMBER_EXPIRATION = 'gigya_session/remember/expiration';

    const XML_PATH_MAPPING_FILE_PATH = 'gigya_section_fieldmapping/general_fieldmapping/mapping_file_path';

    const XML_PATH_GENERAL = 'gigya_section/general';

    const XML_PATH_DEBUG_MODE = 'gigya_advanced/debug_mode/debug_mode';

    // Screen-sets configuration
    const XML_PATH_LOGIN_DESKTOP_SCREENSET_ID = 'gigya_screensets/login_registration/desktop_screenset_id';
    const XML_PATH_LOGIN_MOBILE_SCREENSET_ID = 'gigya_screensets/login_registration/mobile_screenset_id';
    const XML_PATH_PROFILE_DESKTOP_SCREENSET_ID = 'gigya_screensets/profile_update/desktop_screenset_id';
    const XML_PATH_PROFILE_MOBILE_SCREENSET_ID = 'gigya_screensets/profile_update/mobile_screenset_id';
    const XML_PATH_PROFILE_CUSTOM_SCREENSETS = 'gigya_screensets/custom_screensets/custom_screenset_dynamic';

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var CookieManagerInterface
     */
    protected CookieManagerInterface $cookieManager;

    /**
     * @var Logger
     */
    protected Logger $logger;

    /**
     * @var string|int|null
     */
    protected string|int|null $remember;

    /**
     * Config constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param CookieManagerInterface $cookieManager
     * @param Logger $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CookieManagerInterface $cookieManager,
        Logger $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->cookieManager = $cookieManager;
        $this->logger = $logger;
        $this->remember = $remember = $this->cookieManager->getCookie('remember');
    }

    /**
     * @return int
     */
    public function isGigyaEnabled(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_GENERAL . '/enable_gigya', 'website');
    }

    /**
     * @return int
     */
    public function getDebugMode(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_DEBUG_MODE, 'website');
    }

    /**
     * @param null $type
     * @return int
     */
    public function getSessionMode($type = null): int
    {
        if ($type == null) {
            $type = $this->isRememberSession() ? 'remember' : 'type';
        }

        $path = $type == 'remember' ? self::XML_PATH_REMEMBER_MODE : self::XML_PATH_SESSION_MODE;

        return (int)$this->scopeConfig->getValue($path, 'website');
    }

    /**
     * @param null $type
     * @return int
     */
    public function getSessionExpiration($type = null): int
    {
        $initialType = empty($type) ? 'empty' : $type;

        if ($type == null) {
            $type = $this->isRememberSession() ? 'remember' : 'session';
        }

        $path = $type == 'remember' ? self::XML_PATH_REMEMBER_EXPIRATION : self::XML_PATH_SESSION_EXPIRATION;

        return (int)$this->scopeConfig->getValue($path, 'website');
    }

    /**
     * @param $remember
     * @return $this
     */
    public function setRemember($remember): static
    {
        $this->remember = (bool) $remember;
        return $this;
    }

    /**
     * @return bool
     */
    public function isRememberSession(): bool
    {
        return (bool) $this->remember;
    }

    /**
     * @return string
     */
    public function getMappingFilePath(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_MAPPING_FILE_PATH, 'website');
    }

    /**
     * @param string $scopeType
     * @param string $scopeCode
     *
     * @return array
     */
    public function getGigyaGeneralConfig($scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null): array
    {
        $generalConfig = $this->scopeConfig->getValue(self::XML_PATH_GENERAL, $scopeType, $scopeCode);
        return empty($generalConfig) ? [] : $generalConfig;
    }

    /**
     * @return string
     */
    public function getMagentoCookiePath(): string
    {
        return (string)$this->scopeConfig->getValue(\Magento\Framework\Session\Config::XML_PATH_COOKIE_PATH, 'website');
    }

    /**
     * @return string
     */
    public function getLoginDesktopScreensetId(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_LOGIN_DESKTOP_SCREENSET_ID, 'website');
    }

    /**
     * @return string
     */
    public function getLoginMobileScreensetId(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_LOGIN_MOBILE_SCREENSET_ID, 'website');
    }

    /**
     * @return string
     */
    public function getProfileDesktopScreensetId(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_PROFILE_DESKTOP_SCREENSET_ID, 'website');
    }

    /**
     * @return string
     */
    public function getProfileMobileScreensetId(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_PROFILE_MOBILE_SCREENSET_ID, 'website');
    }

    /**
     * @return string
     */
    public function getCustomScreensets(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_PROFILE_CUSTOM_SCREENSETS, 'website');
    }
}

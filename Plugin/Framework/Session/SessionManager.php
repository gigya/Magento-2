<?php

namespace Gigya\GigyaIM\Plugin\Framework\Session;

use Closure;
use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\SessionException;
use Magento\Framework\Session\SessionManager as MagentoSessionManager;

class SessionManager
{
    /**
     * @var GigyaConfig
     */
    protected $config;

    /**
     * @var State
     */
    protected $state;

    /**
     * SessionManager constructor.
     * @param GigyaConfig $config
     * @param State $state
     */
    public function __construct(
        GigyaConfig $config,
        State $state
    ) {
        $this->config = $config;
        $this->state = $state;
    }

    /**
     * As SessionManager::renewCookie method it is private, the workaound to do now let it to be called it is to do not
     * allow a cookie lifetime to be returned by method SessionManager::getCookieLifetime. That's because the first thing
     * that SessionManager::renewCookie checks it is if a cookie lifetime configuration exists, if does not, it won't
     * renew the cookies
     *
     * Defaults to true, keeping Magento defaults behavior
     *
     * @var bool
     */
    protected $allowCookieLifetime = true;

    /**
     * @param MagentoSessionManager $subject
     * @param Closure                                  $proceed
     *
     * @return mixed
     * @throws SessionException
     */
    public function aroundStart(MagentoSessionManager $subject, Closure $proceed)
    {
        try {
            $areaCode = $this->state->getAreaCode();
        } catch (LocalizedException $e) {
            $areaCode = null;
        }
        $sessionMode = $this->config->getSessionMode();
        if ($areaCode != 'frontend' || $sessionMode != GigyaConfig::SESSION_MODE_FIXED) {
            return $proceed();
        }

        $this->allowCookieLifetime = false;

        /**
         * Original method SessionManager::start could throw exception, in that case, keep the original behavior, but it
         * is necessary to set property allowCookieLifetime to true before
         */
        try {
            $result = $proceed();
        } catch (SessionException $e) {
            $this->allowCookieLifetime = true;
            throw $e;
        }

        $this->allowCookieLifetime = true;
        return $result;
    }

    public function afterGetCookieLifetime($subject, $result)
    {
        return $this->allowCookieLifetime ? $result : false;
    }
}

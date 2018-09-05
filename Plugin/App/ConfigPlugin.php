<?php

namespace Gigya\GigyaIM\Plugin\App;

use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ConfigPlugin
{
    /**
     * Arbitrarily set 10 years as 'endless' session duration. That should be sufficient.
     */
    const ENDLESS_SESSION_LIFETIME = 315360000; /* 10 years in seconds */

    /**
     * @param ScopeConfigInterface $subject
     * @param \Closure $proceed
     * @param string $path
     * @param string $scope
     * @param $scopeCode
     * @return int|mixed
     */
    public function aroundGetValue(
        ScopeConfigInterface $subject,
        \Closure $proceed,
        $path = null,
        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $scopeCode = null
    )
    {
        if ($path == GigyaConfig::XML_PATH_SESSION_EXPIRATION) {

            switch($subject->getValue(GigyaConfig::XML_PATH_SESSION_MODE)) {

                case GigyaConfig::SESSION_MODE_BROWSER_INSTANCE :
                    return 0;

                case GigyaConfig::SESSION_MODE_ENDLESS :
                    return self::ENDLESS_SESSION_LIFETIME;
            }
        }

        return $proceed($path, $scope, $scopeCode);
    }
}
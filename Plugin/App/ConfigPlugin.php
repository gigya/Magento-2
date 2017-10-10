<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Plugin\App;

use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ConfigPlugin
{
    /**
     * Arbitrarily set 10 years as 'endless' session duration. That should be sufficient.
     */
    const ENDLESS_SESSION_LIFETIME = 60 * 60 * 24 * 365 * 10;

    /**
     *
     *
     * @param ScopeConfigInterface $subject
     * @param \Closure $proceed
     * @param null $path
     * @param string $scope
     * @param null $scopeCode
     * @return int|void
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
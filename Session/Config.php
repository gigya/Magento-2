<?php

namespace Gigya\GigyaIM\Session;

use Magento\Framework\Session\Config as MagentoSessionConfig;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Filesystem;
use Gigya\GigyaIM\Model\Config as GigyaConfig;

class Config extends MagentoSessionConfig
{
    /**
     * @param \Magento\Framework\ValidatorFactory $validatorFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Stdlib\StringUtils $stringHelper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param Filesystem $filesystem
     * @param DeploymentConfig $deploymentConfig
     * @param string $scopeType
     * @param string $lifetimePath
     * @param GigyaConfig $gigyaConfig
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function __construct(
        \Magento\Framework\ValidatorFactory $validatorFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Stdlib\StringUtils $stringHelper,
        \Magento\Framework\App\RequestInterface $request,
        Filesystem $filesystem,
        DeploymentConfig $deploymentConfig,
        GigyaConfig $gigyaConfig,
        $scopeType,
        $lifetimePath = self::XML_PATH_COOKIE_LIFETIME
    ) {
        if ($gigyaConfig->isGigyaEnabled()) {
            $remember = $request->getParam('remember');
            $remember = $remember == 'true' ? 1 : 0;
            $gigyaConfig->setRemember($remember);

            if ($gigyaConfig->isRememberSession()) {
                $lifetimePath = GigyaConfig::XML_PATH_REMEMBER_EXPIRATION;
            } else {
                $lifetimePath = GigyaConfig::XML_PATH_SESSION_EXPIRATION;
            }
        }

        return parent::__construct(
            $validatorFactory,
            $scopeConfig,
            $stringHelper,
            $request,
            $filesystem,
            $deploymentConfig,
            $scopeType,
            $lifetimePath
        );
    }
}
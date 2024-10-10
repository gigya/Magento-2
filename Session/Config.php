<?php

namespace Gigya\GigyaIM\Session;

use Magento\Framework\Session\Config as MagentoSessionConfig;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Filesystem;
use Magento\Framework\ValidatorFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\App\RequestInterface;
use Gigya\GigyaIM\Model\Config as GigyaConfig;

class Config extends MagentoSessionConfig
{
    /**
     * Config constructor.
     * @param ValidatorFactory $validatorFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param StringUtils $stringHelper
     * @param RequestInterface $request
     * @param Filesystem $filesystem
     * @param DeploymentConfig $deploymentConfig
     * @param GigyaConfig $gigyaConfig
     * @param string $scopeType
     * @param string $lifetimePath
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function __construct(
        ValidatorFactory $validatorFactory,
        ScopeConfigInterface $scopeConfig,
        StringUtils $stringHelper,
        RequestInterface $request,
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
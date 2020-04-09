<?php

namespace Gigya\GigyaIM\Encryption;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Encryption\KeyValidator;
use Magento\Framework\Math\Random;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;
use Gigya\GigyaIM\Helper\GigyaEncryptorHelper;
use Magento\Store\Model\ScopeInterface;

class Encryptor extends \Magento\Framework\Encryption\Encryptor
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * If true will use Gigya Encryptor instead of Magento
     * @var bool
     */
    protected $useGigyaEncryptor = false;

    /**
     * @var string
     */
    protected $gigyaEncryptKey;

    /**
     * @var GigyaEncryptorHelper
     */
    protected $gigyaEncryptorHelper;

    /**
     * Encryptor constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param GigyaEncryptorHelper $gigyaEncryptorHelper
     * @param Random $random
     * @param DeploymentConfig $deploymentConfig
     * @param KeyValidator|null $keyValidator
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        GigyaEncryptorHelper $gigyaEncryptorHelper,
        Random $random,
        DeploymentConfig $deploymentConfig,
        KeyValidator $keyValidator = null
    ) {
        parent::__construct($random, $deploymentConfig, $keyValidator);

        $this->scopeConfig = $scopeConfig;
        $this->gigyaEncryptorHelper = $gigyaEncryptorHelper;

        $this->initEncryptor();
    }

    public function initEncryptor($scopeType = ScopeInterface::SCOPE_WEBSITE, $scopeCode = null)
    {
        $keyFileLocation = $this->scopeConfig->getValue(
            'gigya_section/general/key_file_location',
            $scopeType,
            $scopeCode
        );

        $gigyaEncryptKey = $this->gigyaEncryptorHelper->getKeyFromFile($keyFileLocation);

        if ($gigyaEncryptKey === false) {
            $this->setUseGigyaEncryptor(false);
        } else {
            $this->gigyaEncryptKey = $gigyaEncryptKey;
            $this->setUseGigyaEncryptor(true);
        }
    }

    /**
     * @return bool
     */
    public function getUseGigyaEncryptor()
    {
        return $this->useGigyaEncryptor;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setUseGigyaEncryptor($value)
    {
        $this->useGigyaEncryptor = $value;
        return $this;
    }

    /**
     * @param string $data
     * @return string
     */
    public function encrypt($data)
    {
        if ($this->getUseGigyaEncryptor()) {
            return GigyaApiHelper::enc($data, $this->gigyaEncryptKey);
        } else {
            return parent::encrypt($data);
        }
    }

    /**
     * @param string $data
     * @return string
     * @throws \Exception
     */
    public function decrypt($data)
    {
        if ($this->getUseGigyaEncryptor()) {
            try {
                return GigyaApiHelper::decrypt($data, $this->gigyaEncryptKey);
            } catch (\Exception $e) {
                echo $e->getMessage(); die;
            }
        } else {
            return parent::decrypt($data);
        }
    }
}
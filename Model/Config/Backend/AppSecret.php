<?php

namespace Gigya\GigyaIM\Model\Config\Backend;

class AppSecret extends \Magento\Config\Model\Config\Backend\Encrypted
{
    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Gigya\GigyaIM\Encryption\Encryptor $encryptor
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Gigya\GigyaIM\Encryption\Encryptor $encryptor,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $encryptor,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Init settings for correct encrypt/decrypt strategy
     */
    public function initEncryptor()
    {
        $this->_encryptor->initEncryptor($this->getScope(), $this->getScopeId());
    }

    /**
     * Magic method called during class un-serialization
     *
     * @return void
     */
    public function __wakeup()
    {
        parent::__wakeup();
        $this->_encryptor = \Magento\Framework\App\ObjectManager::getInstance()->get(
            \Gigya\GigyaIM\Encryption\Encryptor::class
        );
        $this->initEncryptor();
    }

    /**
     * Decrypt value after loading
     *
     * @return void
     */
    protected function _afterLoad()
    {
        $this->initEncryptor();
        parent::_afterLoad();;
    }

    /**
     * Encrypt value before saving
     *
     * @return void
     */
    public function beforeSave()
    {
        $this->initEncryptor();
        parent::beforeSave();
    }

    /**
     * Process config value
     *
     * @param string $value
     * @return string
     */
    public function processValue($value)
    {
        $this->initEncryptor();
        parent::processValue($value);
    }
}
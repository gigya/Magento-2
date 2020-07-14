<?php

namespace Gigya\GigyaIM\Model\Config\Backend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Gigya\GigyaIM\Helper\GigyaEncryptorHelper;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;

class KeyFileLocation extends \Magento\Framework\App\Config\Value
{
    /**
     * @var GigyaEncryptorHelper
     */
    protected $gigyaEncryptorHelper;

    /**
     * @param GigyaEncryptorHelper $gigyaEncryptorHelper
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        GigyaEncryptorHelper $gigyaEncryptorHelper,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);

        $this->gigyaEncryptorHelper = $gigyaEncryptorHelper;
    }

	/**
	 * @return \Gigya\GigyaIM\Model\Config\Backend\KeyFileLocation
	 *
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
    public function beforeSave()
    {
        $keyFileLocation = $this->getValue();

        if (empty($keyFileLocation) === false) {
        	try {
            	$gigyaEncryptKey = $this->gigyaEncryptorHelper->getKeyFromFile($this->getValue());
			} catch (FileSystemException $e) {
				throw new LocalizedException(__("Invalid or empty key file provided"));
			}

            if ($gigyaEncryptKey === false) {
                throw new LocalizedException(__("Invalid or empty key file provided"));
            }
        }

        return parent::beforeSave();
    }
}
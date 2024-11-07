<?php

namespace Gigya\GigyaIM\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Gigya\GigyaIM\Helper\GigyaEncryptorHelper;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class KeyFileLocation extends Value
{
    /**
     * @var GigyaEncryptorHelper
     */
    protected $gigyaEncryptorHelper;

    /**
     * @param GigyaEncryptorHelper $gigyaEncryptorHelper
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        GigyaEncryptorHelper $gigyaEncryptorHelper,
        Context              $context,
        Registry             $registry,
        ScopeConfigInterface $config,
        TypeListInterface    $cacheTypeList,
        AbstractResource     $resource = null,
        AbstractDb           $resourceCollection = null,
        array                $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);

        $this->gigyaEncryptorHelper = $gigyaEncryptorHelper;
    }

    /**
     * @return KeyFileLocation
     *
     * @throws LocalizedException
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

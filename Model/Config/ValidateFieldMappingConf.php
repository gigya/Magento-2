<?php

namespace Gigya\GigyaIM\Model\Config;

use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Magento\Customer\Model\ResourceModel\Customer;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Customer sharing config model
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class ValidateFieldMappingConf extends Value
{
    /**
     * @var GigyaLogger
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param StoreManagerInterface $storeManager
     * @param Customer $customerResource
     * @param GigyaMageHelper $gigyaMageHelper
     * @param GigyaLogger $logger
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        StoreManagerInterface $storeManager,
        Customer $customerResource,
        GigyaMageHelper $gigyaMageHelper,
        GigyaLogger $logger,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->logger = $logger;

        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return $this
     *
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        if (isset($this->_data['fieldset_data']) == false) {
            return $this;
        }

        $fieldMappingFilePath = $this->_data['fieldset_data']['mapping_file_path'];

        if (!empty($fieldMappingFilePath) and !file_exists($fieldMappingFilePath)) {
            $message = __('The field mapping file was not found. Please check the file path and try again.');
            $this->logger->error($message);
            throw new LocalizedException($message);
        } elseif (file_exists($fieldMappingFilePath)) {
            $mappingJson = file_get_contents($fieldMappingFilePath);
            if (!json_decode($mappingJson)) {
                $message = __('The field mapping file is empty or has invalid JSON. Please verify the correctness of the file\'s contents.');
                $this->logger->error($message);
                throw new LocalizedException($message);
            }
        }

        return $this;
    }
}

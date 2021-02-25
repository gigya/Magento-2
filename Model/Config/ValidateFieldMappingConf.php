<?php

namespace Gigya\GigyaIM\Model\Config;

use Magento\Framework\Exception\LocalizedException;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * Customer sharing config model
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class ValidateFieldMappingConf extends \Magento\Framework\App\Config\Value
{
	/**
     * @var GigyaLogger
     */
	protected $logger;

	/**
	 * Constructor
	 *
	 * @param \Magento\Framework\Model\Context $context
	 * @param \Magento\Framework\Registry $registry
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
	 * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManager
	 * @param \Magento\Customer\Model\ResourceModel\Customer $customerResource
     * @param GigyaLogger $logger
	 * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
	 * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
	 * @param array $data
	 */
	public function __construct(
		\Magento\Framework\Model\Context $context,
		\Magento\Framework\Registry $registry,
		\Magento\Framework\App\Config\ScopeConfigInterface $config,
		\Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Customer\Model\ResourceModel\Customer $customerResource,
		\Gigya\GigyaIM\Helper\GigyaMageHelper $gigyaMageHelper,
		GigyaLogger $logger,
		\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
		\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
		array $data = []
	) {
		$this->logger = $logger;

		parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
	}

	/**
	 * @return $this
	 *
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	public function beforeSave() {
		if (isset($this->_data['fieldset_data']) == false)
		{
			return $this;
		}

		$fieldMappingFilePath = $this->_data['fieldset_data']['mapping_file_path'];

		if (!empty($fieldMappingFilePath) and !file_exists($fieldMappingFilePath))
		{
			$message = __('The field mapping file was not found. Please check the file path and try again.');
			$this->logger->error($message);
			throw new LocalizedException($message);
		}
		elseif (file_exists($fieldMappingFilePath))
		{
			$mappingJson = file_get_contents($fieldMappingFilePath);
			if (!json_decode($mappingJson))
			{
				$message = __('The field mapping file is empty or has invalid JSON. Please verify the correctness of the file\'s contents.');
				$this->logger->error($message);
				throw new LocalizedException($message);
			}
		}

		return $this;
	}
}

<?php

namespace Gigya\GigyaIM\Model\Config;

use Magento\Framework\Exception\LocalizedException;

/**
 * Customer sharing config model
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class ValidateFieldMappingConf extends \Magento\Framework\App\Config\Value
{
	/** @var  \Gigya\GigyaIM\Helper\GigyaMageHelper */
	protected $gigyaMageHelper;

	/**
	 * Constructor
	 *
	 * @param \Magento\Framework\Model\Context $context
	 * @param \Magento\Framework\Registry $registry
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
	 * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManager
	 * @param \Magento\Customer\Model\ResourceModel\Customer $customerResource
	 * @param \Gigya\GigyaIM\Helper\GigyaMageHelper $gigyaMageHelper
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
		\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
		\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
		array $data = []
	) {
		$this->gigyaMageHelper = $gigyaMageHelper;
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
			$this->gigyaMageHelper->gigyaLog($message, 'error');
			throw new LocalizedException($message);
		}
		elseif (file_exists($fieldMappingFilePath))
		{
			$mappingJson = file_get_contents($fieldMappingFilePath);
			if (!json_decode($mappingJson))
			{
				$message = __('The field mapping file is empty or has invalid JSON. Please verify the correctness of the file\'s contents.');
				$this->gigyaMageHelper->gigyaLog($message, 'error');
				throw new LocalizedException($message);
			}
		}

		return $this;
	}
}

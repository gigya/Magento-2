<?php

namespace Gigya\GigyaIM\Model\Config;

require_once 'vendor/aws/aws-sdk-php/src/functions.php';

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Magento\Checkout\Exception;
use Magento\Framework\Exception\LocalizedException;

class ValidateAWSDetails extends \Magento\Framework\App\Config\Value
{
	/** @var \Magento\Customer\Model\ResourceModel\Customer */
	protected $_customerResource;

	/** @var  \Magento\Store\Model\StoreManagerInterface */
	protected $_storeManager;

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
		$this->_storeManager = $storeManager;
		$this->_customerResource = $customerResource;
		$this->gigyaMageHelper = $gigyaMageHelper;
		parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
	}

	/**
	 * Check for email duplicates before saving customers sharing options
	 *
	 * @return $this
	 *
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	public function beforeSave()
	{
		/* Get submitted settings */
		$is_enabled = intval($this->_data['groups']['deletion_general']['fields']['deletion_is_enabled']['value']);

		if ($is_enabled)
		{
			$aws_region = $this->_data['fieldset_data']['deletion_aws_region'];
			$aws_bucket = $this->_data['fieldset_data']['deletion_aws_bucket'];
			$aws_access_key = $this->_data['fieldset_data']['deletion_aws_access_key'];
			$aws_secret_key = $this->_data['fieldset_data']['deletion_aws_secret_key'];
			$aws_directory = $this->_data['fieldset_data']['deletion_aws_directory'];

			if (empty($aws_region) or empty($aws_bucket) or empty($aws_access_key) or empty($aws_secret_key))
				throw new LocalizedException(__('Could not saved settings. One of the required parameters is missing.'));

			if (!class_exists('Aws\\S3\\S3Client'))
				throw new LocalizedException(__("Could not save settings. AWS PHP SDK is not installed on your system. Please install the package aws\\aws-sdk-php to continue."));
			try {
				$s3_client = new S3Client(array(
					'region' => $aws_region,
					'version' => 'latest',
					'credentials' => array(
						'key' => $aws_access_key,
						'secret' => $aws_secret_key,
					),
				));

				$s3_client->listObjects( array(
					'Bucket' => $aws_bucket,
					'Prefix' => $aws_directory,
				) );
			}
			catch (S3Exception $e)
			{
				throw new LocalizedException(
					__(
						"Could not save settings. AWS authentication failed with error message: {$e->getMessage()} ."
					)
				);
			}

			/* Create object manager and reset the settings to newly submitted */
	//		$this->gigyaMageHelper->setApiKey($api_key);
	//		$this->gigyaMageHelper->setApiDomain($domain);
	//		$this->gigyaMageHelper->setAppKey($app_key);
	//		$this->gigyaMageHelper->setKeyFileLocation($key_file_location);
	//		$this->gigyaMageHelper->setAppSecret();
	//		$gigyaApiHelper = $this->gigyaMageHelper->getGigyaApiHelper();
	//
	//		/* Make the call to Gigya REST API */
	//		$param = array("filter" => 'full');
	//		try {
	//			$gigyaApiHelper->sendApiCall("accounts.getSchema", $param);
	//		} catch (\Gigya\CmsStarterKit\sdk\GSApiException $e) {
	//			$this->gigyaMageHelper->gigyaLog(
	//				"Error while trying to save gigya settings. " . $e->getErrorCode() .
	//				" " .$e->getMessage() . " " . $e->getCallId()
	//			);
	//			throw new \Magento\Framework\Exception\LocalizedException(
	//				__(
	//					"Could not save settings. Gigya API test failed with error message: {$e->getMessage()} ."
	//				)
	//			);
	//		}
		}

		return $this;
	}
}
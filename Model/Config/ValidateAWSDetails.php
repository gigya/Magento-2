<?php

namespace Gigya\GigyaIM\Model\Config;

//require_once 'vendor/aws/aws-sdk-php/src/functions.php';

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Encryption\EncryptorInterface;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

class ValidateAWSDetails extends \Magento\Framework\App\Config\Value
{
	/** @var \Magento\Customer\Model\ResourceModel\Customer */
	protected $_customerResource;

	/** @var  \Magento\Store\Model\StoreManagerInterface */
	protected $_storeManager;

	/** @var  \Gigya\GigyaIM\Helper\GigyaMageHelper */
	protected $gigyaMageHelper;

	/** @var EncryptorInterface */
	protected $encryptor;

	/** @var GigyaLogger */
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
	 * @param \Gigya\GigyaIM\Helper\GigyaMageHelper $gigyaMageHelper
	 * @param EncryptorInterface $encryptor
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
		EncryptorInterface $encryptor,
		GigyaLogger $logger,
		\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
		\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
		array $data = []
	) {
		$this->_storeManager = $storeManager;
		$this->_customerResource = $customerResource;
		$this->gigyaMageHelper = $gigyaMageHelper;
		$this->encryptor = $encryptor;
		$this->logger = $logger;

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
		$is_enabled = (isset($this->_data['groups']['deletion_general']['fields']['deletion_is_enabled']['value']))
			? intval($this->_data['groups']['deletion_general']['fields']['deletion_is_enabled']['value'])
			: false;

		if ($is_enabled)
		{
			$aws_region = $this->_data['fieldset_data']['deletion_aws_region'];
			$aws_bucket = $this->_data['fieldset_data']['deletion_aws_bucket'];
			$aws_access_key = $this->_data['fieldset_data']['deletion_aws_access_key'];
			$aws_secret_key = $this->_data['fieldset_data']['deletion_aws_secret_key'];
			$aws_directory = $this->_data['fieldset_data']['deletion_aws_directory'];

			if (empty($aws_region) or empty($aws_bucket) or empty($aws_access_key) or empty($aws_secret_key))
				throw new LocalizedException(__('Could not saved settings. One of the required parameters is missing.'));

			/* Verify that AWS is installed and the authentication details are correct */
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
				$this->logger->error('Could not connect to AWS with entered details. Error message: '.$e->getMessage());
				throw new LocalizedException(
					__(
						"Could not save settings. AWS authentication failed with error code: {$e->getAwsErrorCode()}. For more details, see the Gigya log."
					)
				);
			}
		}

		return $this;
	}
}
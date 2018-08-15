<?php

namespace Gigya\GigyaIM\Model\Cron;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Gigya\GigyaIM\Helper\GigyaUserDeletionHelper;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Cron\Model\Schedule;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Registry;

class UserDeletion
{
	/** @var GigyaLogger */
	protected $logger;

	/** @var ScopeConfigInterface */
	protected $scopeConfig;

	/** @var WriterInterface */
	protected $configWriter;

	/** @var CustomerRepository */
	protected $customerRepository;

	/** @var CustomerFactory */
	protected $customerFactory;

	/** @var Registry */
	protected $registry;

	/** @var GigyaUserDeletionHelper */
	private $helper;

	/**
	 * UserDeletion constructor.
	 *
	 * @param GigyaLogger $logger
	 * @param Context $context
	 * @param CustomerRepository $customerRepository
	 * @param CustomerFactory $customerFactory
	 * @param WriterInterface $configWriter
	 * @param GigyaUserDeletionHelper $gigyaUserDeletionHelper
	 * @param Registry $registry
	 */
	public function __construct(
		GigyaLogger $logger,
		Context $context,
		CustomerRepository $customerRepository,
		CustomerFactory $customerFactory,
		WriterInterface $configWriter,
		GigyaUserDeletionHelper $gigyaUserDeletionHelper,
		Registry $registry
	) {
		$this->logger = $logger;
		$this->scopeConfig = $context->getScopeConfig();
		$this->configWriter = $configWriter;
		$this->customerRepository = $customerRepository;
		$this->customerFactory = $customerFactory;
		$this->helper = $gigyaUserDeletionHelper;
		$this->registry = $registry;
	}

	protected function getS3FileList($credentials)
	{
		$files = array();
		try {
			$s3_client = new S3Client(array(
				'region' => $credentials['region'],
				'version' => 'latest',
				'credentials' => array(
					'key' => $credentials['access_key'],
					'secret' => $credentials['secret_key'],
				),
			));

			/* Works up to 1000 objects! */
			$aws_object_list = $s3_client->listObjects(array(
				'Bucket' => $credentials['bucket'],
				'Prefix' => $credentials['directory'],
			));
			foreach ($aws_object_list as $key => $object_list) {
				if ($key == 'Contents') {
					foreach ($object_list as $object) {
						/* If last successful run is unknown, or if known take only the files modified after that last run */
						$object_pathinfo = pathinfo($object['Key']);
						if (isset($object_pathinfo['extension']) and $object_pathinfo['extension'] === 'csv') {
							$files[] = $object['Key'];
						}
					}
				}
			}
		} catch (\Exception $e) {
			$this->logger->error('Error connecting Gigya user deletion to AWS A3 on Get File List: ' . $e->getMessage() . '. Please check your credentials.');
			return false;
		}

		return $files;
	}

	protected function getFileList($type = 'S3', $params = array())
	{
		switch ($type) {
			case 'S3':
				return $this->getS3FileList($params);
				break;
		}

		return false;
	}

	protected function getS3FileContents($file, $credentials)
	{
		try {
			$s3_client = new S3Client(
				array(
					'region' => $credentials['region'],
					'version' => 'latest',
					'credentials' => array(
						'key' => $credentials['access_key'],
						'secret' => $credentials['secret_key'],
//						'secret' => $this->user_deletion_helper::decrypt($this->settings['aws_secret_key'], SECURE_AUTH_KEY),
					),
				)
			);

			$s3_client->getObject(array(
				'Bucket' => $credentials['bucket'],
				'Key' => $file,
				'SaveAs' => 'gigya_user_deletion.tmp',
			));

			$csv_contents = file_get_contents('gigya_user_deletion.tmp');
			if (file_exists('gigya_user_deletion.tmp')) {
				unlink('gigya_user_deletion.tmp');
			}
		} catch (S3Exception $e) {
			error_log('Error connecting Gigya user deletion to AWS A3 on Get File Contents: ' . $e->getMessage() . '. Please check your credentials.');
			return false;
		}

		return $csv_contents;
	}

	protected function getFileContents($type, $file, $params)
	{
		switch ($type) {
			case 'S3':
				return $this->getS3FileContents($file, $params);
				break;
		}

		return false;
	}

	/**
	 * @param string $csv_string
	 *
	 * @return array
	 */
	protected function getGigyaUserIDs($csv_string)
	{
		$csv_array = (!empty($csv_string)) ? array_map('trim', explode("\n", $csv_string)) : array();
		array_shift($csv_array);

		return $csv_array;
	}

	/**
	 * @param string $uid_type ENUM: Can be 'gigya' or 'magento'
	 * @param array $uid_array List of UIDs to delete
	 * @param array $failed_users List of UIDs that weren't found in the DB
	 *
	 * @return array
	 *
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	protected function deleteUsers($uid_type, $uid_array, &$failed_users)
	{
		$deletion_type = $this->scopeConfig->getValue('gigya_delete/deletion_general/deletion_type');

//		$this->logger->info('Hard-coded user found: '.var_export($this->helper->searchCustomersByAttributeValue('gigya_uid', 'e8bcae66a06d4f1885a4bcdd4a1e82a3')[0]->getId(), true)); ////
		foreach ($uid_array as $gigya_uid) {
			/* Get Magento entity ID for each Gigya UID */
			if ($uid_type == 'gigya') {
				$magento_users = $this->helper->searchCustomersByAttributeValue('gigya_uid', $gigya_uid);
				if (!empty($magento_users)) {
					$magento_user = $magento_users[0];
					$magento_uid = $magento_user->getId();

					$this->logger->info('User found: ' . $magento_user->getEmail()); ////
//					$this->logger->info(get_class($magento_user));
					$this->logger->info('Attribute: '.$magento_user->getCustomAttribute('gigya_username')->getValue()); ////

					if ($deletion_type == 'soft_delete') {
						try {
							$timestamp = time();
							$magento_user->setCustomAttribute('gigya_deleted_timestamp', $timestamp);
							$this->customerRepository->save($magento_user);
						} catch (\Exception $e) {
							$this->logger->error('Error soft-deleting user: ' . $e->getMessage());
						}
					} elseif ($deletion_type == 'hard_delete') {
						$this->registry->register('isSecureArea', true);
						$customer = $this->customerFactory->create()->load($magento_uid);
						$customer->delete();
					}
				} else {
					$this->logger->info('User NOT found with Gigya UID: ' . $gigya_uid);
				}
			}
		}

		return array(); ////
	}

	/**
	 * @param Schedule $schedule
	 *
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	public function execute(Schedule $schedule)
	{
		$start_time = time();

		$this->logger->info('Gigya deletion pseudo cron started at ' . date("Y-m-d H:i:s")); ////

		$enable_job = $this->scopeConfig->getValue('gigya_delete/deletion_general/deletion_is_enabled', 'website');

		$job_frequency = $this->scopeConfig->getValue('gigya_delete/deletion_general/deletion_job_frequency',
			'website');
		$email_success = $this->scopeConfig->getValue('gigya_delete/deletion_general/deletion_email_success',
			'website');
		$email_failure = $this->scopeConfig->getValue('gigya_delete/deletion_general/deletion_email_failure',
			'website');

		if ($enable_job) {
			$last_run = $this->scopeConfig->getValue('gigya_delete/deletion_general/last_run', 'website');

			$aws_credentials['region'] = $this->scopeConfig->getValue('gigya_delete/deletion_aws_details/deletion_aws_region',
				'website');
			$aws_credentials['bucket'] = $this->scopeConfig->getValue('gigya_delete/deletion_aws_details/deletion_aws_bucket',
				'website');
			$aws_credentials['access_key'] = $this->scopeConfig->getValue('gigya_delete/deletion_aws_details/deletion_aws_access_key',
				'website');
			$aws_credentials['secret_key'] = $this->scopeConfig->getValue('gigya_delete/deletion_aws_details/deletion_aws_secret_key',
				'website');
			$aws_credentials['directory'] = $this->scopeConfig->getValue('gigya_delete/deletion_aws_details/deletion_aws_directory',
				'website');

			$this->logger->info('Gigya deletion cron started at ' . date("Y-m-d H:i:s"));

			$job_failed = true;
			$failed_count = 0;

			if (($start_time - $last_run) > $job_frequency) {
				$this->logger->info('Gigya deletion cron last run: ' . $last_run);

				$files = $this->getFileList('S3', $aws_credentials);

				if (is_array($files)) {
					/* Get only the files that have not been processed */
//					if (count($files) > 0) {
//						$query = $wpdb->prepare("SELECT * FROM {$gigya_user_deletion_table} WHERE filename IN (" . implode(', ',
//								array_fill(0, count($files), '%s')) . ")", $files);
//						$files = array_diff($files, array_column($wpdb->get_results($query, ARRAY_A), 'filename'));
//						if (($file_count = count($files)) === 0) {
//							$job_failed = false;
//						}
//					} else {
//						$job_failed = false;
//					}
//
					foreach ($files as $file) {
						$csv = $this->getS3FileContents($file, $aws_credentials);
						$user_array = $this->getGigyaUserIDs($csv);
						$deleted_users = $this->deleteUsers('gigya', $user_array, $failed_users);

						if ($csv === false or !empty($user_array) and (!is_array($deleted_users) or empty($deleted_users))) {
							$failed_count++;
						} else /* Job succeeded or succeeded with errors */ {
							$job_failed = false;

							/* Mark file as processed */
//							$wpdb->insert($gigya_user_deletion_table, array(
//								'filename' => $file,
//								'time_processed' => time(),
//							));
						}

//						$this->logger->info('File contents for file '.$file.': '.$csv); ////
					}
				} else {
					$job_failed = false;
				}
			}

////		$this->logger->info(implode(', ', array($enable_job, $email_success, $email_failure)));
////		$this->logger->info(implode(', ', array($aws_region, $aws_bucket, $aws_access_key, $aws_secret_key, $aws_directory)));

			$this->configWriter->save('gigya_delete/deletion_general/last_run', time(), 'website');
		}
	}
}
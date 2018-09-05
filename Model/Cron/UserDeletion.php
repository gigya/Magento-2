<?php

namespace Gigya\GigyaIM\Model\Cron;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Gigya\GigyaIM\Helper\GigyaUserDeletionHelper;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Gigya\GigyaIM\Model\ResourceModel\ConnectionFactory;
use Magento\Cron\Model\Schedule;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Event\Manager;

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

	/** @var AdapterInterface */
	protected $connection;

	/** @var ConnectionFactory */
	protected $connectionFactory;

	/** @var ResourceConnection */
	protected $resourceConnection;

	/** @var Attribute */
	protected $eavAttribute;

	/** @var StoreManagerInterface */
	protected $storeManager;

	/** @var Manager */
	private $eventManager;

	/** @var GigyaUserDeletionHelper */
	private $helper;

	/** @var array */
	private $email_success;

	/** @var array */
	private $email_failure;

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
	 * @param ResourceConnection $resourceConnection
	 * @param ConnectionFactory $connectionFactory
	 * @param Attribute $attribute
	 * @param StoreManagerInterface $storeManager
	 * @param Manager $eventManager
	 */
	public function __construct(
		GigyaLogger $logger,
		Context $context,
		CustomerRepository $customerRepository,
		CustomerFactory $customerFactory,
		WriterInterface $configWriter,
		GigyaUserDeletionHelper $gigyaUserDeletionHelper,
		Registry $registry,
		ResourceConnection $resourceConnection,
		ConnectionFactory $connectionFactory,
		Attribute $attribute,
		StoreManagerInterface $storeManager,
		Manager $eventManager
	) {
		$this->logger = $logger;
		$this->scopeConfig = $context->getScopeConfig();
		$this->configWriter = $configWriter;
		$this->customerRepository = $customerRepository;
		$this->customerFactory = $customerFactory;
		$this->helper = $gigyaUserDeletionHelper;
		$this->registry = $registry;
		$this->connectionFactory = $connectionFactory;
		$this->connection = $this->connectionFactory->getNewConnection();
		$this->resourceConnection = $resourceConnection;
		$this->eavAttribute = $attribute;
		$this->storeManager = $storeManager;
		$this->eventManager = $eventManager;
	}

	private function getEmails()
	{
		$email_success = str_replace(' ', '', $this->scopeConfig->getValue('gigya_delete/deletion_general/deletion_email_success'));
		$email_failure = str_replace(' ', '', $this->scopeConfig->getValue('gigya_delete/deletion_general/deletion_email_failure'));
		if (empty($email_failure)) {
			$email_failure = $email_success;
		}

		if (strpos($email_success, ',') !== false) {
			$this->email_success = explode(',', $email_success);
		} else {
			$this->email_success = explode(';', $email_success);
		}

		if (strpos($email_failure, ',') !== false) {
			$this->email_failure = explode(',', $email_failure);
		} else {
			$this->email_failure = explode(';', $email_failure);
		}
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
		$deleted_users = array();

		$this->eventManager->dispatch('gigya_user_deletion_soft_delete_before');

		foreach ($uid_array as $gigya_uid) {
			/* Get Magento entity ID for each Gigya UID */
			if ($uid_type == 'gigya') {
				$magento_users = $this->helper->getCustomersByAttributeValue('gigya_uid', $gigya_uid);

				if (!empty($magento_users)) {
					$magento_user = $magento_users[0];
					$magento_uid = $magento_user->getId();

					if ($deletion_type == 'soft_delete') {
						try {
							/* Check if the user has already been deleted */
							$attribute_id = $this->eavAttribute->getIdByCode('customer', 'gigya_deleted_timestamp');

							$gigya_deleted_timestamp = $magento_user->getCustomAttribute('gigya_deleted_timestamp');

							if (empty($gigya_deleted_timestamp)) {
								$timestamp = time();

								$deletion_data = array(
									'attribute_id' => $attribute_id,
									'entity_id' => $magento_uid,
									'value' => $timestamp,
								);

								if ($this->connection->insert($this->resourceConnection->getTableName('customer_entity_int'), $deletion_data)) {
									$deleted_users[] = $gigya_uid;

									$this->eventManager->dispatch('gigya_user_deletion_soft_delete_after', array('deletionData' => $deletion_data));
								} else {
									$failed_users[] = $gigya_uid;
								}
							} else {
								$this->logger->info('Gigya deletion cron: User ' . $magento_uid . ' deleted at: ' . $gigya_deleted_timestamp->getValue());
							}
						} catch (\Exception $e) {
							$this->logger->error('Gigya deletion cron: Error soft-deleting user: ' . $e->getMessage());
						}
					} elseif ($deletion_type == 'hard_delete') {
						$this->registry->register('isSecureArea', true);
						$this->customerRepository->delete($magento_user);
					}
				} else {
					$this->logger->info('Gigya deletion cron: User not found with Gigya UID: ' . $gigya_uid);
				}
			}
		}

		return $deleted_users;
	}

	/**
	 * @param Schedule $schedule
	 *
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
	public function execute()
	{
		$start_time = time();

		$enable_job = $this->scopeConfig->getValue('gigya_delete/deletion_general/deletion_is_enabled');
		$this->getEmails();

		if ($enable_job) {
			$last_run = $this->scopeConfig->getValue('gigya_delete/deletion_general/last_run');

			$aws_credentials = $this->scopeConfig->getValue('gigya_delete/deletion_aws_details');
			foreach ($aws_credentials as $key => $credential) {
				$aws_credentials[str_replace('deletion_aws_', '', $key)] = $credential;
				unset($aws_credentials[$key]);
			}

			$this->logger->info('Gigya deletion cron started at ' . date("Y-m-d H:i:s"));

			$job_failed = true;
			$failed_count = 0;

			$this->logger->info('Gigya deletion cron last run: ' . $last_run);

			$files = $this->getFileList('S3', $aws_credentials);

			$deleted_users = array();
			$total_deleted_users = 0;

			if (is_array($files)) {
				/* Get only the files that have not been processed */
				$select_deletion_rows = $this->connection
					->select()
					->from($this->resourceConnection->getTableName('gigya_user_deletion'))
					->reset(\Zend_Db_Select::COLUMNS)
					->columns('filename');
				$processed_files = array_column($this->connection->fetchAll($select_deletion_rows, [],
					\Zend_Db::FETCH_ASSOC), 'filename');

				foreach ($files as $file) {
					if (!in_array($file, $processed_files)) {
						$csv = $this->getS3FileContents($file, $aws_credentials);
						$user_array = array_filter($this->getGigyaUserIDs($csv));
						$deleted_users = $this->deleteUsers('gigya', $user_array, $failed_users);
						$total_deleted_users += count($deleted_users);

						if ($csv === false or empty($deleted_users)) {
							$failed_count++;
						} else /* Job succeeded or succeeded with errors */ {
							$job_failed = false;

							/* Mark file as processed */
							$this->connection->insert($this->resourceConnection->getTableName('gigya_user_deletion'),
								array(
									'filename' => $file,
									'time_processed' => time(),
								));
						}
					} else {
						$this->logger->info('Gigya deletion cron: file ' . $file . ' skipped because it has already been processed.');
					}
				}
			} elseif ($files === false) { /* On error from AWS S3 */
				$job_failed = true;
			} else {
				$job_failed = false;
			}

			/* Generic email sender init */
			$email_sender = new \Zend_Mail();

			if ($job_failed) {
				/* Params for email */
				$job_status = 'failed';
				$email_to = $this->email_failure;
				$email_body = 'Job failed. No users were deleted. It is possible that no new users were processed, or that some users could not be deleted. Please consult the Gigya log for more info.';

				$this->logger->warning('Gigya user deletion job from ' . $start_time . ' failed (no users were deleted). It is possible that no new users were processed, or that some users could not be deleted. Please consult the rest of the log for more info.');
			} else {
				/* Params for email */
				$job_status = 'completed';
				$email_to = $this->email_success;
				$email_body = 'Job succeeded or completed with errors. ' . count($deleted_users) . ' deleted, ' . $failed_count . ' failed.';

				$this->logger->info('Gigya user deletion job from ' . $start_time . ' was completed successfully.');
			}

			try {
				$email_subject = 'Gigya user deletion ' . $job_status . ' on website ' . $this->storeManager->getStore()->getBaseUrl();
				$email_from = $email_to[0];

				$email_sender->setSubject($email_subject);
				$email_sender->setBodyText($email_body);
				$email_sender->setFrom($email_from, 'Gigya user deletion cron');
				$email_sender->addTo($email_to);
				$email_sender->send();

				$this->logger->info('Gigya deletion cron: mail sent to: ' . implode(', ', $email_to) . ' with status ' . $job_status);
			} catch (\Zend_Mail_Exception $e) {
				$this->logger->warning('Gigya deletion cron: unable to send email: ' . $e->getMessage());
			}

			$this->configWriter->save('gigya_delete/deletion_general/last_run', time());
		}
	}
}
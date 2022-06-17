<?php

namespace Gigya\GigyaIM\Model\Cron;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Gigya\GigyaIM\Helper\GigyaCronHelper;
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
use Magento\Framework\Event\Manager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Db;
use Zend_Db_Select;
use Zend_Mail;

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
    protected $eventManager;

    /** @var GigyaCronHelper */
    protected $helper;

    /** @var array */
    protected $email_success;

    /** @var array */
    protected $email_failure;

    /**
     * UserDeletion constructor.
     *
     * @param GigyaLogger           $logger
     * @param Context               $context
     * @param CustomerRepository    $customerRepository
     * @param CustomerFactory       $customerFactory
     * @param WriterInterface       $configWriter
     * @param GigyaCronHelper       $gigyaUserDeletionHelper
     * @param Registry              $registry
     * @param ResourceConnection    $resourceConnection
     * @param ConnectionFactory     $connectionFactory
     * @param Attribute             $attribute
     * @param StoreManagerInterface $storeManager
     * @param Manager               $eventManager
     */
    public function __construct(
        GigyaLogger $logger,
        Context $context,
        CustomerRepository $customerRepository,
        CustomerFactory $customerFactory,
        WriterInterface $configWriter,
        GigyaCronHelper $gigyaUserDeletionHelper,
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

    public function getEmails()
    {
        $email_success = str_replace(' ', '', (string)$this->scopeConfig->getValue('gigya_delete/deletion_general/deletion_email_success'));
        $email_failure = str_replace(' ', '', (string)$this->scopeConfig->getValue('gigya_delete/deletion_general/deletion_email_failure'));
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
        $files = [];
        try {
            $s3_client = new S3Client([
                'region' => $credentials['region'],
                'version' => 'latest',
                'credentials' => [
                    'key' => $credentials['access_key'],
                    'secret' => $credentials['secret_key'],
                ],
            ]);

            /* Works up to 1000 objects! */
            $aws_object_list = $s3_client->listObjects([
                'Bucket' => $credentials['bucket'],
                'Prefix' => $credentials['directory'],
            ]);
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
            $this->logger->critical('Error connecting Gigya user deletion to AWS A3 on Get File List: ' . $e->getMessage() . '. Please check your credentials.');
            return false;
        }

        return $files;
    }

    protected function getFileList($type = 'S3', $params = [])
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
                [
                    'region' => $credentials['region'],
                    'version' => 'latest',
                    'credentials' => [
                        'key' => $credentials['access_key'],
                        'secret' => $credentials['secret_key'],
                    ],
                ]
            );

            $s3_client->getObject([
                'Bucket' => $credentials['bucket'],
                'Key' => $file,
                'SaveAs' => 'gigya_user_deletion.tmp',
            ]);

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
        if (empty($csv_string)) {
            return [];
        }

        $csv_array = array_map('str_getcsv', explode("\n", $csv_string));
        array_shift($csv_array);

        return array_filter(array_column($csv_array, 0));
    }

    /**
     * @param string $uid_type ENUM: Can be 'gigya' or 'magento'
     * @param array $uid_array List of UIDs to delete
     * @param array $failed_users List of UIDs that weren't found in the DB
     *
     * @return array
     *
     * @throws LocalizedException
     */
    protected function deleteUsers($uid_type, $uid_array, &$failed_users)
    {
        $deletion_type = $this->scopeConfig->getValue('gigya_delete/deletion_general/deletion_type');
        $deleted_users = [];

        $this->eventManager->dispatch('gigya_user_deletion_soft_delete_before');

        /* Setting elevated permissions for full user deletion */
        if ($deletion_type == 'hard_delete') {
            try {
                $this->registry->register('isSecureArea', true);
            } catch (\RuntimeException $e) {
                $this->logger->critical('Gigya deletion cron: Error elevating permissions for user deletion: ' . $e->getMessage());
            }
        }

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

                                $deletion_data = [
                                    'attribute_id' => $attribute_id,
                                    'entity_id' => $magento_uid,
                                    'value' => $timestamp,
                                ];

                                if ($this->connection->insert($this->resourceConnection->getTableName('customer_entity_int'), $deletion_data)) {
                                    $deleted_users[] = $gigya_uid;

                                    $this->eventManager->dispatch('gigya_user_deletion_soft_delete_after', ['deletionData' => $deletion_data]);
                                } else {
                                    $failed_users[] = $gigya_uid;
                                }
                            } else {
                                $this->logger->info('Gigya deletion cron: Magento user ' . $magento_uid . ' already soft-deleted at: ' . $gigya_deleted_timestamp->getValue());
                            }
                        } catch (\Exception $e) {
                            $this->logger->critical('Gigya deletion cron: Error soft-deleting user: ' . $e->getMessage());
                        }
                    } elseif ($deletion_type == 'hard_delete') {
                        try {
                            $this->customerRepository->delete($magento_user);
                            $deleted_users[] = $gigya_uid;
                        } catch (\Exception $e) {
                            $this->logger->critical('Gigya deletion cron: Error fully deleting user: ' . $e->getMessage());
                        }
                    }
                } else {
                    $this->logger->critical('Gigya deletion cron: User not found with Gigya UID: ' . $gigya_uid);
                }
            }
        }

        return $deleted_users;
    }

    /**
     * @throws LocalizedException
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

            $deleted_users = [];
            $total_deleted_users = 0;

            if (is_array($files)) {
                /* Get only the files that have not been processed */
                $select_deletion_rows = $this->connection
                    ->select()
                    ->from($this->resourceConnection->getTableName('gigya_user_deletion'))
                    ->reset(Zend_Db_Select::COLUMNS)
                    ->columns('filename');
                $processed_files = array_column($this->connection->fetchAll(
                    $select_deletion_rows,
                    [],
                    Zend_Db::FETCH_ASSOC
                ), 'filename');

                foreach ($files as $file) {
                    if (!in_array($file, $processed_files)) {
                        $csv = $this->getS3FileContents($file, $aws_credentials);
                        $user_array = $this->getGigyaUserIDs($csv);
                        $deleted_users = $this->deleteUsers('gigya', $user_array, $failed_users);
                        $total_deleted_users += count($deleted_users);

                        $no_users_behavior = $this->scopeConfig->getValue('gigya_delete/deletion_general/deletion_not_found_behavior');
                        if (($csv === false) or (empty($deleted_users) and $no_users_behavior === 'failure')) {
                            $failed_count++;
                        } else /* Job succeeded or succeeded with errors */ {
                            $job_failed = false;

                            /* Mark file as processed */
                            $this->connection->insert(
                                $this->resourceConnection->getTableName('gigya_user_deletion'),
                                [
                                    'filename' => $file,
                                    'time_processed' => time(),
                                ]
                            );
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
            $email_sender = new Zend_Mail();

            if ($job_failed) {
                /* Params for email */
                $job_status = 'failed';
                $email_to = $this->email_failure;
                $email_body = 'Job failed. No users were deleted. It is possible that no new users were processed, or that some users could not be deleted. Please consult the Gigya log for more info.';

                $this->logger->critical('Gigya user deletion job from ' . $start_time . ' failed (no users were deleted). It is possible that no new users were processed, or that some users could not be deleted. Please consult the rest of the log for more info.');
            } else {
                $deleted_user_count = count($deleted_users);

                /* Params for email */
                $job_status = 'completed';
                $email_to = $this->email_success;
                $email_body = 'Job succeeded or completed with errors. ' . $deleted_user_count . ' deleted, ' . $failed_count . ' failed.';

                $this->logger->info(
                    'Gigya user deletion job from ' . $start_time . ' succeeded or completed with errors. '
                    . ($deleted_user_count ?? '0') . (($deleted_user_count === 1) ? ' user was' : ' users were') . ' deleted.'
                );
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
                $this->logger->info('Gigya deletion cron: unable to send email: ' . $e->getMessage());
            }

            $this->configWriter->save('gigya_delete/deletion_general/last_run', time());
        }
    }
}

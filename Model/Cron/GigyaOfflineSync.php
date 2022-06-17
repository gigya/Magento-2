<?php

namespace Gigya\GigyaIM\Model\Cron;

use Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping\FieldMappingException;
use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;
use Gigya\GigyaIM\Helper\GigyaCronHelper;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Gigya\GigyaIM\Model\FieldMapping\GigyaFromMagento;
use Gigya\GigyaIM\Model\FieldMapping\GigyaToMagento;
use Gigya\GigyaIM\Model\GigyaCustomerFieldsUpdater;
use Gigya\PHP\GSResponse;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\Context;

class GigyaOfflineSync
{
    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var GigyaMageHelper */
    protected $gigyaMageHelper;

    /** @var GigyaLogger */
    protected $logger;

    /** @var GigyaApiHelper */
    protected $gigyaApiHelper;

    /** @var WriterInterface */
    protected $configWriter;

    /** @var GigyaCustomerFieldsUpdater */
    protected $customerFieldsUpdater;

    /** @var GigyaFromMagento */
    protected $gigyaFromMagento;

    /** @var GigyaCronHelper */
    protected $gigyaCronHelper;

    /** @var CustomerRepository */
    protected $customerRepository;

    /** @var GigyaMageHelper */
    protected $gigyaSyncHelper;

    /** @var GigyaToMagento */
    protected $gigyaToMagento;

    const MAX_USERS = 300; /* Maximum users to get from accounts.search */
    const RETRY_WAIT = 60; /* Time to wait between retry if Gigya fails */
    const UPDATE_DELAY = 60000; /* 60 seconds */
    const CRON_NAME = 'Gigya offline sync';

    /**
     * GigyaOfflineSync constructor.
     *
     * @param GigyaLogger                $logger
     * @param Context                    $context
     * @param GigyaMageHelper            $gigyaMageHelper
     * @param GigyaSyncHelper            $gigyaSyncHelper
     * @param WriterInterface            $configWriter
     * @param GigyaCustomerFieldsUpdater $customerFieldsUpdater
     * @param GigyaFromMagento           $gigyaFromMagento
     * @param GigyaCronHelper            $gigyaUserDeletionHelper
     * @param CustomerRepository         $customerRepository
     * @param GigyaToMagento             $gigyaToMagento
     */
    public function __construct(
        GigyaLogger $logger,
        Context $context,
        GigyaMageHelper $gigyaMageHelper,
        GigyaSyncHelper $gigyaSyncHelper,
        WriterInterface $configWriter,
        GigyaCustomerFieldsUpdater $customerFieldsUpdater,
        GigyaFromMagento $gigyaFromMagento,
        GigyaCronHelper $gigyaUserDeletionHelper,
        CustomerRepository $customerRepository,
        GigyaToMagento $gigyaToMagento
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $context->getScopeConfig();
        $this->gigyaMageHelper = $gigyaMageHelper;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->configWriter = $configWriter;
        $this->customerFieldsUpdater = $customerFieldsUpdater;
        $this->gigyaFromMagento = $gigyaFromMagento;
        $this->gigyaCronHelper = $gigyaUserDeletionHelper;
        $this->customerRepository = $customerRepository;
        $this->gigyaToMagento = $gigyaToMagento;
    }

    /**
     * @param string     $gigyaQuery
     * @param \Exception &$gigyaException
     * @param int        $triesLeft
     *
     * @return array|false
     */
    public function searchGigyaUsers($gigyaQuery, &$gigyaException, $triesLeft = 1)
    {
        if ($triesLeft > 0) {
            $this->gigyaApiHelper = $this->gigyaMageHelper->getGigyaApiHelper();

            try {
                /** @var GSResponse $gigya_data */
                $gigyaUsers = $this->gigyaApiHelper->searchGigyaUsers($gigyaQuery, true);

                return $gigyaUsers;
            } catch (\Exception $e) {
                sleep(self::RETRY_WAIT);
                $gigyaException = ['message' => $e->getMessage(), 'code' => $e->getCode()];
                return $this->searchGigyaUsers($gigyaQuery, $gigyaException, $triesLeft - 1);
            }
        }

        return false;
    }

    private function handleError($errorMessage, $emailsOnFailure, $processedUsers, $usersNotFound)
    {
        $this->gigyaCronHelper->sendEmail(self::CRON_NAME, 'failed', $emailsOnFailure, $processedUsers, $usersNotFound);
        $this->logger->error('Error on cron ' . self::CRON_NAME . ': ' . $errorMessage . '.');
    }

    public function execute()
    {
        $enableSync = $this->scopeConfig->getValue('gigya_section_fieldmapping/offline_sync/offline_sync_is_enabled', 'website');

        $emailsOnSuccess = $this->gigyaCronHelper->getEmailsFromConfig('gigya_section_fieldmapping/offline_sync/sync_email_success');
        $emailsOnFailure = $this->gigyaCronHelper->getEmailsFromConfig('gigya_section_fieldmapping/offline_sync/sync_email_failure');

        $this->logger->info(self::CRON_NAME . ' started. Time: ' . date("Y-m-d H:i:s"));

        if ($enableSync) {
            if (!($lastCustomerUpdate = $this->scopeConfig->getValue('gigya_section_fieldmapping/offline_sync/last_customer_update'))) {
                $lastCustomerUpdate = 0;
            }

            $gigyaQuery = 'SELECT * FROM accounts';
            if ($lastCustomerUpdate) {
                $gigyaQuery .= ' WHERE lastUpdatedTimestamp > ' . $lastCustomerUpdate;
            }
            $gigyaQuery .= ' ORDER BY lastUpdatedTimestamp ASC LIMIT ' . self::MAX_USERS;

            try {
                $processedUsers = 0;
                $usersNotFound = 0;

                /* Get user data from Gigya */
                $gigyaUsers = $this->searchGigyaUsers($gigyaQuery, $gigyaException, 3);
                if ($gigyaUsers === false) {
                    $this->handleError($gigyaException['message'], $emailsOnFailure, $processedUsers, $usersNotFound);
                    throw new \Exception($gigyaException['message'], $gigyaException['code']);
                }

                foreach ($gigyaUsers as $gigyaUser) {
                    /* Abort if user does not have UID */
                    $gigyaUID = $gigyaUser->getUID();
                    if (empty($gigyaUID)) {
                        $message = 'User with the following data does not have a UID. Unable to process. ' . json_encode($gigyaUser);
                        $this->handleError($message, $emailsOnFailure, $processedUsers, $usersNotFound);
                        throw new \Exception($message);
                    }

                    /* Abort if user does not have a valid lastUpdatedTimestamp */
                    if (empty($gigyaUser->getLastUpdatedTimestamp())) {
                        $message = 'User ' . $gigyaUID . ' does not have a valid last updated timestamp';
                        $this->handleError($message, $emailsOnFailure, $processedUsers, $usersNotFound);
                        throw new \Exception($message);
                    }

                    /* Run sync (field mapping) */
                    $magentoCustomer = $this->gigyaCronHelper->getFirstCustomerByAttributeValue('gigya_uid', $gigyaUser->getUID()); /* Retrieve Magento 2 customer by Gigya UID */
                    if (!empty($magentoCustomer)) {
                        try {
                            $this->gigyaToMagento->run($magentoCustomer, $gigyaUser, true); /* Enriches Magento customer with Gigya data */
                            $this->customerRepository->save($magentoCustomer);

                            /* Save the successful save timestamp */
                            $lastCustomerUpdate = $gigyaUser->getLastUpdatedTimestamp();
                            if ($lastCustomerUpdate) {
                                $lastCustomerUpdate -= self::UPDATE_DELAY; /* Create a window of UPDATE_DELAY in which users will be re-synced on the next run (if applicable). This is to compensate for possible replication delays in accounts.search */
                                $this->configWriter->save('gigya_section_fieldmapping/offline_sync/last_customer_update', $lastCustomerUpdate);
                            }

                            $processedUsers++;
                        } catch (\Exception $e) {
                            $message = 'Error syncing user. Gigya UID: ' . $gigyaUID;
                            $this->handleError($message, $emailsOnFailure, $processedUsers, $usersNotFound);
                            throw new FieldMappingException($message);
                        }
                    } else {
                        $usersNotFound++;

                        $this->logger->warning(self::CRON_NAME . ': User not found. Gigya UID: ' . $gigyaUID);
                    }
                }

                /* Successful run completion actions */
                $this->configWriter->save('gigya_section_fieldmapping/offline_sync/last_run', round(microtime(true) * 1000));
                $this->logger->info(self::CRON_NAME . ' completed. Users processed: ' . $processedUsers . (($usersNotFound) ? '. Users not found: ' . $usersNotFound : ''));
                $status = ($usersNotFound > 0) ? 'completed with errors' : 'succeeded';
                $this->gigyaCronHelper->sendEmail(self::CRON_NAME, $status, $emailsOnSuccess, $processedUsers, $usersNotFound);
            } catch (\Exception $e) {
                $this->handleError($e->getMessage(), $emailsOnFailure, $processedUsers, $usersNotFound);
                $this->logger->error('Error on cron ' . self::CRON_NAME . ': ' . $e->getMessage() . '.');
            }
        }
    }
}
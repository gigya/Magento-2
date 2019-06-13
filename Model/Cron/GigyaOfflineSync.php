<?php

namespace Gigya\GigyaIM\Model\Cron;

use Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping\FieldMappingException;
use Gigya\GigyaIM\Helper\CmsStarterKit\sdk\GSResponse;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Helper\GigyaUserDeletionHelper;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Gigya\GigyaIM\Model\FieldMapping\GigyaFromMagento;
use Gigya\GigyaIM\Model\FieldMapping\GigyaToMagento;
use Gigya\GigyaIM\Model\GigyaCustomerFieldsUpdater;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

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

	/** @var GigyaUserDeletionHelper */
	protected $userDeletionHelper;

	/** @var CustomerRepository */
	protected $customerRepository;

	/** @var GigyaMageHelper */
	protected $gigyaSyncHelper;

	/** @var GigyaToMagento */
	protected $gigyaToMagento;

	const MAX_USERS = 1000; /* Maximum users to get from accounts.search */
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
	 * @param GigyaUserDeletionHelper    $gigyaUserDeletionHelper
	 * @param CustomerRepository         $customerRepository
	 * @param CustomerFactory            $customerFactory
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
		GigyaUserDeletionHelper $gigyaUserDeletionHelper,
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
		$this->userDeletionHelper = $gigyaUserDeletionHelper;
		$this->customerRepository = $customerRepository;
		$this->gigyaToMagento = $gigyaToMagento;
	}

	public function execute()
	{
		$enableSync = $this->scopeConfig->getValue('gigya_section_fieldmapping/offline_sync/offline_sync_is_enabled', 'website');
		$isDebugMode = boolval($this->gigyaMageHelper->getDebug());

		$this->logger->info(self::CRON_NAME . ' started. Time: ' . date("Y-m-d H:i:s"));

		if ($enableSync) {
			$this->gigyaApiHelper = $this->gigyaMageHelper->getGigyaApiHelper();

			$gigyaQuery = 'SELECT * FROM accounts';
			if ($lastRun = $this->scopeConfig->getValue('gigya_section_fieldmapping/offline_sync/last_run')) {
				$gigyaQuery .= ' WHERE lastUpdatedTimestamp > ' . $lastRun;
			}
			$gigyaQuery .= ' ORDER BY lastUpdatedTimestamp ASC LIMIT ' . self::MAX_USERS;

			try {
				$processedUsers = 0;
				$usersNotFound = 0;

				if (!($lastCustomerUpdate = $this->scopeConfig->getValue('gigya_section_fieldmapping/offline_sync/last_customer_update'))) {
					$lastCustomerUpdate = 0;
				}

				/** @var GSResponse $gigya_data */
				$gigyaUsers = $this->gigyaApiHelper->searchGigyaUsers($gigyaQuery);

				foreach ($gigyaUsers as $gigyaUser) {
					/* Abort if user does not have UID */
					$gigyaUID = $gigyaUser->getUID();
					if (empty($gigyaUID)) {
						throw new \Exception('User with the following data does not have a UID. Unable to process. ' . json_encode($gigyaUser));
					}

					/* Abort if user does not have a valid lastUpdatedTimestamp */
					if (empty($gigyaUser->getLastUpdatedTimestamp())) {
						throw new \Exception('User ' . $gigyaUID . ' does not have a valid last updated timestamp');
					}

					/* Run sync (field mapping) */
					$magentoCustomer = $this->userDeletionHelper->getFirstCustomerByAttributeValue('gigya_uid', $gigyaUser->getUID()); /* Retrieve Magento 2 customer by Gigya UID */
					if (!empty($magentoCustomer)) {
						try {
							$this->gigyaToMagento->run($magentoCustomer, $gigyaUser); /* Enriches Magento customer with Gigya data */
							$this->customerRepository->save($magentoCustomer);

							/* Save the successful save timestamp */
							$lastCustomerUpdate = $gigyaUser->getLastUpdatedTimestamp();
							if ($lastCustomerUpdate) {
								$lastCustomerUpdate -= self::UPDATE_DELAY; /* Create a window of UPDATE_DELAY in which users will be re-synced on the next run (if applicable). This is to compensate for possible replication delays in accounts.search */
								$this->configWriter->save('gigya_section_fieldmapping/offline_sync/last_customer_update', $lastCustomerUpdate);
							}

							$processedUsers++;
						} catch (\Exception $e) {
							$this->logger->error(self::CRON_NAME . ': Error syncing user. Gigya UID: ' . $gigyaUID);
							throw new FieldMappingException('Error syncing user. Gigya UID: ' . $gigyaUID);
						}
					} else {
						$usersNotFound++;
						if ($isDebugMode) {
							$this->logger->warning(self::CRON_NAME . ': User not found. Gigya UID: ' . $gigyaUID);
						}
					}

					/* Saves the successful run timestamp */
					$this->configWriter->save('gigya_section_fieldmapping/offline_sync/last_run', round(microtime(true) * 1000));
				}

				$this->logger->info(self::CRON_NAME . ' completed. Users processed: ' . $processedUsers . (($usersNotFound) ? '. Users not found: ' . $usersNotFound : ''));
			} catch (\Exception $e) {
				$this->logger->error('Error on cron ' . self::CRON_NAME . ': ' . $e->getMessage() . '.');
			}
		}
	}
}
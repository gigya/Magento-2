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

	const MAX_USERS = 1000;
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
		$enable_sync = $this->scopeConfig->getValue('gigya_section_fieldmapping/offline_sync/offline_sync_is_enabled', 'website');
		$is_debug_mode = boolval($this->gigyaMageHelper->getDebug());
		$max_users = 1000;

		$this->logger->info(self::CRON_NAME . ' started. Time: ' . date("Y-m-d H:i:s"));

		if ($enable_sync) {
			$this->gigyaApiHelper = $this->gigyaMageHelper->getGigyaApiHelper();

			$gigya_query = 'SELECT * FROM accounts';
			if ($last_run = $this->scopeConfig->getValue('gigya_section_fieldmapping/offline_sync/last_run')) {
				$gigya_query .= ' WHERE lastUpdatedTimestamp > ' . $last_run;
			}
			$gigya_query .= ' ORDER BY lastUpdatedTimestamp ASC LIMIT ' . $max_users;

			try {
				$processed_users = 0;
				if (!($last_customer_update = $this->scopeConfig->getValue('gigya_section_fieldmapping/offline_sync/last_customer_update'))) {
					$last_customer_update = 0;
				}

				/** @var GSResponse $gigya_data */
				$gigya_users = $this->gigyaApiHelper->searchGigyaUsers($gigya_query);

				foreach ($gigya_users as $gigya_user) {
					/* Abort if user does not have UID */
					$gigya_uid = $gigya_user->getUID();
					if (empty($gigya_uid)) {
						throw new \Exception('User with the following data does not have a UID. Unable to process. ' . json_encode($gigya_user));
					}

					/* Abort if user does not have a valid lastUpdatedTimestamp */
					if (empty($gigya_user->getLastUpdatedTimestamp())) {
						throw new \Exception('User ' . $gigya_uid . ' does not have a valid last updated timestamp');
					}

					/* Run sync (field mapping) */
					$magento_customer = $this->userDeletionHelper->getFirstCustomerByAttributeValue(
						'gigya_uid', $gigya_user->getUID()
					); /* Retrieve Magento 2 customer by Gigya UID */
					if (!empty($magento_customer)) {
						try {
							$this->gigyaToMagento->run($magento_customer, $gigya_user); /* Enriches Magento customer with Gigya data */
							$this->customerRepository->save($magento_customer);

							/* Saves the successful save timestamp */
							$last_customer_update = $gigya_user->getLastUpdatedTimestamp();
							if ($last_customer_update) {
								$this->configWriter->save('gigya_section_fieldmapping/offline_sync/last_customer_update', $last_customer_update);
							}

							$processed_users++;
						} catch (\Exception $e) {
							$this->logger->error(self::CRON_NAME . ': Error syncing user. Gigya UID: ' . $gigya_uid);
							throw new FieldMappingException('Error syncing user. Gigya UID: ' . $gigya_uid);
						}
					} else {
						if ($is_debug_mode) {
							$this->logger->warning(self::CRON_NAME . ': User not found. Gigya UID: ' . $gigya_uid);
						}
					}

					/* Saves the successful run timestamp */
					$this->configWriter->save('gigya_section_fieldmapping/offline_sync/last_run', round(microtime(true) * 1000));
				}

				$this->logger->info(self::CRON_NAME . ' completed. Users processed: ' . $processed_users);
			} catch (\Exception $e) {
				$this->logger->error('Error on cron ' . self::CRON_NAME . ': ' . $e->getMessage() . '.');
			}
		}
	}
}
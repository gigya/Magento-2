<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model\Cron;

use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Observer\SyncCustomerToGigyaObserver;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * RetryGigyaUpdate
 *
 * Fetch the db table 'gigya_sync_retry' to perform the Gigya update retries.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class RetryGigyaUpdate
{
    /** @var  ResourceConnection */
    protected $resourceConnection;

    /** @var  CustomerRepositoryInterface\ */
    protected $customerRepository;

    /** @var  GigyaSyncHelper */
    protected $gigyaSyncHelper;

    /** @var  GigyaLogger */
    protected $logger;

    /**
     * RetryGigyaUpdate constructor.
     *
     * @param ResourceConnection $connection
     * @param CustomerRepositoryInterface $customerRepository
     * @param GigyaLogger $logger
     */
    public function __construct(
        ResourceConnection $connection,
        CustomerRepositoryInterface $customerRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        GigyaLogger $logger
    )
    {
        $this->resourceConnection = $connection;
        $this->customerRepository = $customerRepository;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Cron\Model\Schedule $schedule
     */
    public function execute(\Magento\Cron\Model\Schedule $schedule)
    {
        $connection = $this->resourceConnection->getConnection('gigya');

        $selectRetryRows = $connection
            ->select()
            ->from('gigya_sync_retry')
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns([ 'customer_entity_id', 'retry_count' ])
            ->where('direction = "' . SyncCustomerToGigyaObserver::DIRECTION_CMS2G . '"');

        $allRetriesRow = $connection->fetchAll($selectRetryRows, [], \Zend_Db::FETCH_ASSOC);

        foreach($allRetriesRow as $retryRow) {
            $customerEntityId = $retryRow['customer_entity_id'];
            $retryCount = 1 + $retryRow['retry_count'];
            try {
                $magentoCustomer = $this->customerRepository->getById($customerEntityId);
                $excludeSyncCms2G = true;
                if (!$this->gigyaSyncHelper->isCustomerIdExcludedFromSync($customerEntityId,
                    GigyaSyncHelper::DIR_CMS2G)
                ) {
                    // We prevent synchronizing the M2 customer data to the Gigya account :
                    // in the context of the retry the Gigya data have already been enriched with the Magento customer
                    $this->gigyaSyncHelper->excludeCustomerIdFromSync($magentoCustomer->getId(),
                        GigyaSyncHelper::DIR_CMS2G);
                    $excludeSyncCms2G = false;
                }
                try {
                    $this->customerRepository->save($magentoCustomer);
                } finally {
                    // If the synchro to Gigya was not already disabled we re-enable it
                    if (!$excludeSyncCms2G) {
                        $this->gigyaSyncHelper->undoExcludeCustomerIdFromSync($magentoCustomer->getId(),
                            GigyaSyncHelper::DIR_CMS2G);
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning('Retry update Gigya failed.',
                    [
                        'customer_entity_id' => $customerEntityId,
                        'retry_count' => $retryCount
                    ]
                );
            }
        }
    }
}
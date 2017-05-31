<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model\Cron;

use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Observer\SyncCustomerToGigyaObserver;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * RetryGigyaUpdateCron
 *
 * Fetch the db table 'gigya_sync_retry' to perform the Gigya update retries.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class RetryGigyaUpdateCron
{
    /** @var  ResourceConnection */
    protected $resourceConnection;

    /** @var  CustomerRepositoryInterface\ */
    protected $customerRepository;

    /** @var  LoggerInterface */
    protected $logger;

    public function __construct(
        ResourceConnection $connection,
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger
    )
    {
        $this->resourceConnection = $connection;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Cron\Model\Schedule $schedule
     */
    public function execute(\Magento\Cron\Model\Schedule $schedule)
    {
        $connection = $this->resourceConnection->getConnection();

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
                //
                $customer = $this->customerRepository->getById($customerEntityId);
                // When the save is performed the observer of the event 'customer_save_before' will forward the data to Gigya
                $this->customerRepository->save($customer);
            } catch (\Exception $e) {
                $this->logger->warning(sprintf('Retry update number [%d] Gigya failed for customer entity id [%d]', $retryCount, $customerEntityId));
            }
        }
    }
}
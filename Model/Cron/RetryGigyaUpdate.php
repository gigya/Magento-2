<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model\Cron;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Model\ResourceModel\ConnectionFactory;
use Gigya\GigyaIM\Observer\SyncCustomerToGigyaObserver;
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
    /** @var  GigyaLogger */
    protected $logger;

    /** @var ConnectionFactory */
    protected $connectionFactory;

    /** @var  GigyaAccountRepositoryInterface */
    protected $gigyaAccountRepository;

    /**
     * RetryGigyaUpdate constructor.
     *
     * @param GigyaLogger $logger
     * @param ConnectionFactory $connectionFactory
     */
    public function __construct(
        GigyaLogger $logger,
        ConnectionFactory $connectionFactory,
        GigyaAccountRepositoryInterface $gigyaAccountRepository
    )
    {
        $this->logger = $logger;
        $this->connectionFactory = $connectionFactory;
        $this->gigyaAccountRepository = $gigyaAccountRepository;
    }

    /**
     * @param \Magento\Cron\Model\Schedule $schedule
     */
    public function execute(\Magento\Cron\Model\Schedule $schedule)
    {
        $connection = $this->connectionFactory->getNewConnection();

        $selectRetryRows = $connection
            ->select()
            ->from('gigya_sync_retry')
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns([ 'customer_entity_id', 'customer_entity_email', 'gigya_uid', 'retry_count' ])
            ->where('direction = "' . SyncCustomerToGigyaObserver::DIRECTION_CMS2G . '"');

        $allRetriesRow = $connection->fetchAll($selectRetryRows, [], \Zend_Db::FETCH_ASSOC);

        foreach($allRetriesRow as $retryRow) {
            $customerEntityId = $retryRow['customer_entity_id'];
            $customerEntityEmail = $retryRow['customer_entity_email'];
            $retryCount = 1 + $retryRow['retry_count'];
            try {
                $gigyaAccountData = $this->gigyaAccountRepository->get($retryRow['gigya_uid']);
                $this->gigyaAccountRepository->update($gigyaAccountData);
            } catch (\Exception $e) {
                $this->logger->warning('Retry update Gigya failed.',
                    [
                        'customer_entity_id' => $customerEntityId,
                        'customer_entity_email' => $customerEntityEmail,
                        'retry_count' => $retryCount,
                        'message' => $e->getMessage()
                    ]
                );
            }
        }
    }
}

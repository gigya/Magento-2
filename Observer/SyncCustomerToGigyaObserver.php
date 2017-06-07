<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Model\GigyaAccountService;
use Gigya\GigyaIM\Model\ResourceModel\ConnectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State as AppState;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * SyncCustomerToGigyaObserver
 *
 * Is triggered when a Gigya data update (synchronizing with the Gigya service) has failed or succeeded.
 *
 * If failure : @see SyncCustomerToGigyaObserver::performUpdateFailure()
 *
 * If success : @see SyncCustomerToGigyaObserver::performUpdateSuccess()
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class SyncCustomerToGigyaObserver implements ObserverInterface
{
    const DIRECTION_CMS2G = 'CMS2G';

    /** @var  ResourceConnection */
    protected $resourceConnection;

    /** @var  GigyaMageHelper */
    protected $gigyaMageHelper;

    /** @var  AppState */
    protected $appState;

    /** @var  GigyaLogger */
    protected $logger;

    /** @var int */
    private $maxGigyaUpdateRetryCount;

    /** @var ConnectionFactory */
    protected $connectionFactory;

    /**
     * SyncCustomerToGigyaObserver constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param GigyaMageHelper $gigyaMageHelper
     * @param AppState $state
     * @param GigyaLogger $logger
     * @param ConnectionFactory $connectionFactory
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        GigyaMageHelper $gigyaMageHelper,
        AppState $state,
        GigyaLogger $logger,
        ConnectionFactory $connectionFactory
    )
    {
        $this->resourceConnection = $resourceConnection;
        $this->gigyaMageHelper = $gigyaMageHelper;
        $this->appState = $state;
        $this->logger = $logger;

        $this->maxGigyaUpdateRetryCount = $this->gigyaMageHelper->getMaxRetryCountForGigyaUpdate();
        $this->connectionFactory = $connectionFactory;
    }

    /**
     * Depending on event GigyaAccountService::EVENT_UPDATE_GIGYA_FAILURE or GigyaAccountService::EVENT_UPDATE_GIGYA_SUCCESS
     * will perform the failure or success algo.
     *
     * @param Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        switch ($observer->getEvent()->getName()) {

            case GigyaAccountService::EVENT_UPDATE_GIGYA_FAILURE :
                $this->performUpdateFailure($observer);
                break;

            case GigyaAccountService::EVENT_UPDATE_GIGYA_SUCCESS :
                $this->performUpdateSuccess($observer);
                break;
        }
    }

    /**
     * Get the rows of db table 'gigya_sync_retry' for the given customer id.
     *
     * @param int $customerEntityId
     * @param AdapterInterface $connection
     * @return array
     */
    protected function getRetriesRows($customerEntityId, $connection)
    {
        $selectRetryRows = $connection
            ->select()
            ->from('gigya_sync_retry')
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns('retry_count')
            ->where('customer_entity_id = :customer_entity_id');

        return $connection->fetchAll(
            $selectRetryRows,
            [ 'customer_entity_id' => $customerEntityId ],
            \Zend_Db::FETCH_ASSOC
        );
    }

    /**
     * If a retry row already exists for this Customer id :
     *  If we are in the 'crontab' area (ie the update has been performed by the automatic update cron) :
     *      . if the max retry attempt has been reached the row is deleted and a critical message is logged
     *      . otherwise the retry_count is incremented, and the Gigya data and the date are updated
     *  Otherwise - not in the 'crontab' area :
     *      . the row is updated with the error message, the Gigya data and the current date, and the retry_count is set to 0
     *
     * If there is no row for this Customer id :
     * . a new row is inserted
     *
     * @param Observer $observer
     * @return void
     */
    protected function performUpdateFailure($observer)
    {
        /** @var integer $customerEntityId */
        $customerEntityId = $observer->getData('customer_entity_id');
        /** @var array $gigyaAccountData with entries uid, profile, data */
        $gigyaAccountData = $observer->getData('gigya_data');
        /** @var string $message */
        $message = $observer->getData('message');

        $binds = [
            'customer_entity_id' => $customerEntityId,
            'gigya_uid' => $gigyaAccountData['uid'],
            'direction' => self::DIRECTION_CMS2G,
            'data' => serialize($gigyaAccountData),
            'message' => $message,
            'retry_count' => 0,
            'date' => date('Y-m-d H:i:s', gmdate('U'))
        ];

        $connection = $this->connectionFactory->getNewConnection();
        $connection->beginTransaction();

        try {
            $allRetriesRow = $this->getRetriesRows($customerEntityId, $connection);

            if (empty($allRetriesRow)) {
                $connection->insert(
                    $this->resourceConnection->getTableName('gigya_sync_retry'),
                    $binds
                );
                $this->logger->debug(
                    'Inserted a new row in gigya_sync_retry for Magento to Gigya retry',
                    [
                        'customer_entity_id' => $customerEntityId,
                        'gigya_data' => $gigyaAccountData,
                        'message' => $message
                    ]
                );
            } else {
                // If failure after an automatic update retry by the cron we increment the retry count
                if ($this->appState->getAreaCode() == Area::AREA_CRONTAB) {
                    $retryCount = (int)$allRetriesRow[0]['retry_count'];
                    if ($retryCount == $this->maxGigyaUpdateRetryCount) {
                        $this->logger->warning(
                            sprintf(
                                'Maximum retry attempts for Magento to Gigya retry has been reached (%d). Retry is now unscheduled.',
                                $this->maxGigyaUpdateRetryCount
                            ),
                            [
                                'customer_entity_id' => $customerEntityId,
                                'gigya_data' => $gigyaAccountData,
                                'message' => $message
                            ]
                        );
                        $connection->delete(
                            'gigya_sync_retry',
                            'customer_entity_id = ' . $customerEntityId
                        );
                    } else {
                        $binds['retry_count'] = ++$retryCount;
                        unset($binds['customer_entity_id']);
                        $connection->update(
                            'gigya_sync_retry',
                            $binds,
                            'customer_entity_id = ' . $customerEntityId
                        );
                    }
                } else { // Failure not in the automatic cron update retry context : set the retry count to 0
                    $binds['retry_count'] = 0;
                    unset($binds['customer_entity_id']);
                    $connection->update(
                        'gigya_sync_retry',
                        $binds,
                        'customer_entity_id = ' . $customerEntityId
                    );
                    $this->logger->debug(
                        'Reset a row in gigya_sync_retry for Magento to Gigya retry',
                        [
                            'customer_entity_id' => $customerEntityId,
                            'gigya_data' => $gigyaAccountData,
                            'message' => $message
                        ]
                    );
                }
            }

            $connection->commit();
        } catch(\Exception $e) {

            $this->logger->critical(
                'Could not log retry entry for Magento to Gigya update. No automatic retry will be performed on it.',
                [
                    'exception' => $e,
                    'customer_entity_id' => $customerEntityId,
                    'gigya_data' => $gigyaAccountData
                ]
            );
            $connection->rollBack();
        }
    }

    /**
     * If a retry row has been stored we will delete it.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    protected function performUpdateSuccess($observer)
    {
        /** @var integer $customerEntityId */
        $customerEntityId = $observer->getData('customer_entity_id');

        $connection = $this->connectionFactory->getNewConnection();
        $connection->beginTransaction();

        $allRetriesRow = $this->getRetriesRows($customerEntityId, $connection);

        if (!empty($allRetriesRow)) {
            try {
                $connection->delete(
                    'gigya_sync_retry',
                    'customer_entity_id = ' . $customerEntityId
                );
                $this->logger->info(
                    'Previously failed retry update Gigya has now succeeded.',
                    [ 'customer_entity_id' => $customerEntityId ]
                );

                $connection->commit();
            } catch (\Exception $e) {
                $this->logger->critical(
                    'Could not remove retry entry for Magento to Gigya update after a successful update on the same customer.',
                    [
                        'exception' => $e,
                        'customer_entity_id' => $customerEntityId
                    ]
                );
                $connection->rollBack();
            }
        }
    }
}
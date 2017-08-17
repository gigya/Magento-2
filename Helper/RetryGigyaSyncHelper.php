<?php
/**
 * Clever-Age
 * Date: 11/05/17
 * Time: 11:19
 */

namespace Gigya\GigyaIM\Helper;

use Gigya\GigyaIM\Exception\RetryGigyaException;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\Context as HelperContext;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Gigya\GigyaIM\Model\ResourceModel\ConnectionFactory;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\Area;

/**
 * RetryGigyaSyncHelper
 *
 * For scheduling, updating and deleting retry entries for Magento from / to Gigya data synchronizing.
 *
 * All functions are run within a unique transaction, that should be at the end commited or roll backed with self::commit() or self::rollBack()
 * When calling a commit or rollBack, a new transaction is opened for further operations.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class RetryGigyaSyncHelper extends GigyaSyncHelper
{
    /** @var  GigyaLogger */
    protected $logger;

    /** @var  ResourceConnection */
    protected $resourceConnection;

    /** @var  ConnectionFactory */
    protected $connectionFactory;

    /** @var AdapterInterface */
    protected $connection;

    /** @var int */
    private $maxGigyaUpdateRetryCount;

    /**
     * RetryGigyaSyncHelper constructor.
     *
     * @param Context $helperContext
     * @param MessageManager $messageManager
     * @param CustomerRepositoryInterface $customerRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param StoreManagerInterface $storeManager
     * @param Session $customerSession
     * @param AppState $state
     * @param Context $context
     * @param GigyaLogger $logger
     * @param ResourceConnection $resourceConnection
     * @param ConnectionFactory $connectionFactory
     * @param GigyaMageHelper $gigyaMageHelper
     */
    public function __construct(
        HelperContext $helperContext,
        MessageManager $messageManager,
        CustomerRepositoryInterface $customerRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        StoreManagerInterface $storeManager,
        Session $customerSession,
        AppState $state,
        Context $context,
        GigyaLogger $logger,
        ResourceConnection $resourceConnection,
        ConnectionFactory $connectionFactory,
        GigyaMageHelper $gigyaMageHelper
    )
    {
        parent::__construct(
            $helperContext,
            $messageManager,
            $customerRepository,
            $searchCriteriaBuilder,
            $filterBuilder,
            $filterGroupBuilder,
            $storeManager,
            $customerSession,
            $state
        );

        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
        $this->connectionFactory = $connectionFactory;
        $this->connection = $this->connectionFactory->getNewConnection();
        $this->maxGigyaUpdateRetryCount = $gigyaMageHelper->getMaxRetryCountForGigyaUpdate();
    }

    /**
     * @inheritdoc
     *
     * In the context of the Gigya data update retry we retrieve the Magento customer directly from its id that shall have been set on $gigyaAccount->getCustomerEntityId()
     * And the logging email is by definition the email set on this very Customer entity.
     *
     */
    public function getMagentoCustomerAndLoggingEmail($gigyaAccount)
    {
        $magentoCustomer = null;
        $customerEntityId = $gigyaAccount->getCustomerEntityId();
        $excludeSyncG2Cms = true;
        if (!$this->isCustomerIdExcludedFromSync($customerEntityId, GigyaSyncHelper::DIR_G2CMS)
        ) {
            // We prevent synchronizing the M2 customer data from the Gigya account : that should be done only on explicit customer save,
            // here the very first action is to load the M2 customer
            $this->excludeCustomerIdFromSync($customerEntityId, GigyaSyncHelper::DIR_G2CMS);
            $excludeSyncG2Cms = false;
        }
        try {
            $magentoCustomer = $this->customerRepository->getById($customerEntityId);
        } finally {
            // If the synchro from Gigya was not already disabled we re-enable it
            if (!$excludeSyncG2Cms) {
                $this->undoExcludeCustomerIdFromSync($magentoCustomer->getId(), GigyaSyncHelper::DIR_G2CMS);
            }
        }

        $loggingEmail = $gigyaAccount->getProfile()->getEmail() ? $gigyaAccount->getProfile()->getEmail() : ($magentoCustomer) ? $magentoCustomer->getEmail() : null;

        return [
            'customer' => $magentoCustomer,
            'logging_email' => $loggingEmail
        ];
    }

    /**
     * Commit the underlying transaction if any is opened, and re begin it.
     */
    public function commit()
    {
        if ($this->connection->getTransactionLevel() != 0) {
            $this->connection->commit();
            $this->connection->beginTransaction();
        }
    }

    /**
     * Roll back the underlying transaction if any is opened, and re begin it.
     */
    public function rollBack()
    {
        if ($this->connection->getTransactionLevel() != 0) {
            $this->connection->rollBack();
            $this->connection->beginTransaction();
        }
    }

    /**
     * Get the current number of retry already performed, if any, for a given customer.
     *
     * @param $direction string self::DIRECTION_CMS2G or self::DIRECTION_G2CMS or self::DIRECTION_BOTH  or null (in that case no check is made on the entry direction)
     * @param int $customerEntityId
     * @return int -1 if no retry is currently scheduled, the retry count otherwise.
     * @throws RetryGigyaException
     */
    public function getCurrentRetryCount($direction, $customerEntityId)
    {
        if ($direction != null && $direction != GigyaSyncHelper::DIR_CMS2G && $direction != GigyaSyncHelper::DIR_G2CMS) {
            throw new RetryGigyaException('Direction value should be within ['.GigyaSyncHelper::DIR_CMS2G.', '.GigyaSyncHelper::DIR_G2CMS.']');
        }

        if ($direction != null && $direction != GigyaSyncHelper::DIR_CMS2G && $direction != GigyaSyncHelper::DIR_G2CMS) {
            throw new RetryGigyaException('Direction value should be within ['.GigyaSyncHelper::DIR_CMS2G.', '.GigyaSyncHelper::DIR_G2CMS.']');
        }

        $where = 'customer_entity_id = ' . $customerEntityId;
        if (!is_null($direction)) {
            $where .= ' AND direction = "' . $direction . '"';
        }

        $selectRetryRows = $this->connection
            ->select()
            ->from('gigya_sync_retry')
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns('retry_count')
            ->where($where);

        $retryRows = $this->connection->fetchAll($selectRetryRows, [], \Zend_Db::FETCH_ASSOC);

        if (empty($retryRows)) {
            return -1;
        } else {
            return (int)$retryRows[0]['retry_count'];
        }
    }

    /**
     * Get all the scheduled retry entries for the given synchronizing direction.
     *
     * @param $direction string self::DIRECTION_CMS2G or self::DIRECTION_G2CMS or self::DIRECTION_BOTH or null (in that case no check is made on the entry direction)
     * @param $uid string Default is null. If not null will get the unique entry scheduled for this Gigya uid.
     * @param $getGigyaData bool Default is false. If not false will include the Gigya data stored on this entry.
     * @throws RetryGigyaException
     * @return array [
     *                 'customer_entity_id' : int,
     *                 'customer_entity_email' : string,
     *                 'gigya_uid' : string,
     *                 'retry_count' : int
     *                 (if $getGigyaData == true) 'data' : json string
     *               ]
     */
    public function getRetryEntries($direction, $uid = null, $getGigyaData = false)
    {
        if ($direction != null && $direction != GigyaSyncHelper::DIR_CMS2G && $direction != GigyaSyncHelper::DIR_G2CMS) {
            throw new RetryGigyaException('Direction value should be within ['.GigyaSyncHelper::DIR_CMS2G.', '.GigyaSyncHelper::DIR_G2CMS.']');
        }

        $where = null;
        if (!is_null($direction)) {
            $where = 'direction = "' . $direction . '"';
        }
        if (!is_null($uid)) {
            if (!is_null($where)) {
                $where .= ' AND ';
            }
            $where .=  'gigya_uid = "'.$uid.'"';
        }

        $columns = [ 'customer_entity_id', 'customer_entity_email', 'gigya_uid', 'retry_count' ];

        if ($getGigyaData == true) {
            $columns[] = 'data';
        }

        $selectRetryRows = $this->connection
            ->select()
            ->from('gigya_sync_retry')
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns($columns);

        if (!is_null($where)) {
            $selectRetryRows = $selectRetryRows->where($where);
        }

        return $this->connection->fetchAll($selectRetryRows, [], \Zend_Db::FETCH_ASSOC);
    }

    /**
     * Create a new retry entry.
     *
     * @param $direction string self::DIRECTION_CMS2G or self::DIRECTION_G2CMS or self::DIRECTION_BOTH
     * @param $binds array [
     *                       'customer_entity_id' : int
     *                       'customer_entity_email' : string
     *                       'gigya_uid': string
     *                       'data' : array [ 'uid', 'profile', 'data' ]
     *                       'message' : string
     *                     ]
     * @return void
     * @throws RetryGigyaException
     */
    protected function createRetryEntry($direction, $binds)
    {
        if ($direction != GigyaSyncHelper::DIR_CMS2G && $direction != GigyaSyncHelper::DIR_G2CMS) {
            throw new RetryGigyaException('Direction value should be within ['.GigyaSyncHelper::DIR_CMS2G.', '.GigyaSyncHelper::DIR_G2CMS.']');
        }

        $binds['data'] = serialize($binds['data']);
        $binds['retry_count'] = 0;
        $binds['date'] = date('Y-m-d H:i:s', gmdate('U'));
        $binds['direction'] = $direction;

        $this->connection->insert(
            $this->resourceConnection->getTableName('gigya_sync_retry'),
            $binds
        );

        $this->logger->debug(
            'Inserted a new row in gigya_sync_retry for '.$direction.' retry',
            [
                'customer_entity_id' => $binds['customer_entity_id'],
                'customer_entity_email' => $binds['customer_entity_email'],
                'gigya_data' => $binds['data'],
                'message' => $binds['message']
            ]
        );
    }

    /**
     * Set the retry count on an existing retry entry.
     *
     * @param $direction string self::DIRECTION_CMS2G or self::DIRECTION_G2CMS or self::DIRECTION_BOTH
     * @param $customerEntityId
     * @param $retryCount
     * @return void
     * @throws RetryGigyaException
     */
    protected function setRetryCount($direction, $customerEntityId, $retryCount)
    {
        if ($direction != GigyaSyncHelper::DIR_CMS2G && $direction != GigyaSyncHelper::DIR_G2CMS) {
            throw new RetryGigyaException('Direction value should be within ['.GigyaSyncHelper::DIR_CMS2G.', '.GigyaSyncHelper::DIR_G2CMS.']');
        }

        $this->connection->update(
            'gigya_sync_retry',
            [
                'direction' => $direction,
                'retry_count' => $retryCount
            ],
            'customer_entity_id = ' . $customerEntityId
        );
    }

    /**
     * Retry count is incremented.
     *
     * @param $direction string self::DIRECTION_CMS2G or self::DIRECTION_G2CMS or self::DIRECTION_BOTH
     * @param $customerEntityId
     * @return void
     * @throws RetryGigyaException
     */
    public function incrementRetryCount($direction, $customerEntityId)
    {
        if ($direction != GigyaSyncHelper::DIR_CMS2G && $direction != GigyaSyncHelper::DIR_G2CMS) {
            throw new RetryGigyaException('Direction value should be within ['.GigyaSyncHelper::DIR_CMS2G.', '.GigyaSyncHelper::DIR_G2CMS.']');
        }

        $retryCount = $this->getCurrentRetryCount(null, $customerEntityId);

        $this->setRetryCount($direction, $customerEntityId, ++$retryCount);

        $this->logger->debug(
            'Increment gigya_sync_retry.retry_count for '.$direction.' retry',
            [
                'customer_entity_id' => $customerEntityId,
                'retry_count' => $retryCount,
                'direction' => $direction
            ]
        );
    }

    /**
     * Delete an existing retry entry.
     *
     * Won't fail if the given customer entity id has no scheduled retry entry.
     *
     * @param $direction string GigyaSyncHelper::DIR_CMS2G or GigyaSyncHelper::DIR_G2CMS or null (in that case no check is made on the entry direction)
     * @param $customerEntityId integer Customer entity id of the row to delete.
     * @param $successMessage string Message to log (info) in case of delete is successful.
     * @param $failureMessage string Message to log (critical) on case of delete is failure.
     * @throws RetryGigyaException
     */
    public function deleteRetryEntry(
        $direction,
        $customerEntityId,
        $successMessage = null,
        $failureMessage = null
    )
    {
        if ($direction != null && $direction != GigyaSyncHelper::DIR_CMS2G && $direction != GigyaSyncHelper::DIR_G2CMS) {
            throw new RetryGigyaException('Direction value should be within ['.GigyaSyncHelper::DIR_CMS2G.', '.GigyaSyncHelper::DIR_G2CMS.']');
        }

        $retryCount = $this->getCurrentRetryCount($direction, $customerEntityId);

        if ($retryCount > -1) {
            try {
                $where = 'customer_entity_id = ' . $customerEntityId;
                if (!is_null($direction)) {
                    $where .= ' AND direction  = "' . $direction . '"';
                }
                $this->connection->delete(
                    'gigya_sync_retry',
                     $where
                );
                if (!is_null($successMessage)) {
                    $this->logger->info(
                        $successMessage,
                        ['customer_entity_id' => $customerEntityId]
                    );
                } else {
                    $this->logger->debug(
                        'Delete row gigya_sync_retry for '.$direction.' retry',
                        ['customer_entity_id' => $customerEntityId]
                    );
                }
            } catch (\Exception $e) {
                $this->connection->rollBack();
                if (!is_null($failureMessage)) {
                    $this->logger->critical(
                        $failureMessage,
                        [
                            'exception' => $e,
                            'customer_entity_id' => $customerEntityId
                        ]
                    );
                } else {
                    $this->logger->critical(
                        'Could not delete row gigya_sync_retry for '.$direction.' retry',
                        ['customer_entity_id' => $customerEntityId]
                    );
                }
            }
        }
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
     * @param $direction string GigyaSyncHelper::DIR_CMS2G or GigyaSyncHelper::DIR_G2CMS
     * @param $customerEntityId int
     * @param $customerEntityEmail string
     * @param $gigyaAccountData array with entries uid, profile, data
     * @param $message string
     * @return void
     * @throws RetryGigyaException
     */
    public function scheduleRetry($direction, $customerEntityId, $customerEntityEmail, $gigyaAccountData, $message) {

        if ($direction != GigyaSyncHelper::DIR_CMS2G && $direction != GigyaSyncHelper::DIR_G2CMS) {
            throw new RetryGigyaException('Direction value should be within ['.GigyaSyncHelper::DIR_CMS2G.', '.GigyaSyncHelper::DIR_G2CMS.']');
        }

        $binds = [
            'customer_entity_id' => $customerEntityId,
            'customer_entity_email' => $customerEntityEmail,
            'gigya_uid' => $gigyaAccountData['uid'],
            'data' => $gigyaAccountData,
            'message' => $message != null ? (strlen($message) > 255 ? substr($message, 0, 255).' ...' : $message) : null
        ];

        try {
            $retryCount = $this->getCurrentRetryCount(null, $customerEntityId);

            if ($retryCount == -1) {
                $this->createRetryEntry($direction, $binds);
            } else {
                // If failure after an automatic update retry by the cron : we increment the retry count
                if ($this->appState->getAreaCode() == Area::AREA_CRONTAB) {
                    if ($retryCount >= $this->maxGigyaUpdateRetryCount - 1) {
                        $this->logger->warning(
                            sprintf(
                                'Maximum retry attempts for '.$direction.' has been reached (%d). Retry is now unscheduled.',
                                $this->maxGigyaUpdateRetryCount
                            ),
                            [
                                'customer_entity_id' => $customerEntityId,
                                'customer_entity_email' => $customerEntityEmail,
                                'gigya_data' => $gigyaAccountData,
                                'message' => $message
                            ]
                        );

                        $this->deleteRetryEntry(null, $customerEntityId);
                    } else {
                        $this->incrementRetryCount($direction, $customerEntityId);
                    }
                } else { // Failure not in the automatic cron update retry context : reset the scheduled retry entry
                    $this->deleteRetryEntry(null, $customerEntityId);
                    $this->createRetryEntry($direction, $binds);
                }
            }

            $this->commit();
        } catch(\Exception $e) {
            $this->rollBack();
            $this->logger->critical(
                'Could not log retry entry for '.$direction.'. No automatic retry will be performed on it.',
                [
                    'exception' => $e,
                    'customer_entity_id' => $customerEntityId,
                    'customer_entity_email' => $customerEntityEmail,
                    'gigya_data' => $gigyaAccountData
                ]
            );
        }
    }
}

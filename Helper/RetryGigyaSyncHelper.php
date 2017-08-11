<?php
/**
 * Clever-Age
 * Date: 11/05/17
 * Time: 11:19
 */

namespace Gigya\GigyaIM\Helper;

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
    const DIRECTION_CMS2G = 'CMS2G';
    const DIRECTION_G2CMS = 'G2CMS';
    const DIRECTION_BOTH = 'BOTH';

    /** @var  GigyaLogger */
    protected $logger;

    /** @var  ResourceConnection */
    protected $resourceConnection;

    /** @var  ConnectionFactory */
    protected $connectionFactory;

    /** @var AdapterInterface */
    protected $connection;

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
        ConnectionFactory $connectionFactory
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
     * @param int $customerEntityId
     * @return int -1 if no retry is currently scheduled, the retry count otherwise.
     */
    public function getCurrentRetryCount($customerEntityId)
    {
        $selectRetryRows = $this->connection
            ->select()
            ->from('gigya_sync_retry')
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns('retry_count')
            ->where('customer_entity_id = :customer_entity_id');

        $retryRows = $this->connection->fetchAll(
            $selectRetryRows,
            [ 'customer_entity_id' => $customerEntityId ],
            \Zend_Db::FETCH_ASSOC
        );

        if (empty($retryRows)) {
            return -1;
        } else {
            return (int)$retryRows[0]['retry_count'];
        }
    }

    /**
     * Get all the scheduled retry entries for the given synchronizing direction.
     *
     * @param $direction string self::DIRECTION_CMS2G or self::DIRECTION_G2CMS or self::DIRECTION_BOTH
     * @param $uid string Default is null. If not null will get the unique entry scheduled for this Gigya uid.
     * @param $getGigyaData bool Default is false. If not false will include the Gigya data stored on this entry.
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
        $where = 'direction = "' . $direction . '"';

        if (!is_null($uid)) {
            $where .=  ' AND gigya_uid = "'.$uid.'"';
        }

        $columns = [ 'customer_entity_id', 'customer_entity_email', 'gigya_uid', 'retry_count' ];

        if ($getGigyaData == true) {
            $columns[] = 'data';
        }

        $selectRetryRows = $this->connection
            ->select()
            ->from('gigya_sync_retry')
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns($columns)
            ->where($where);

        return $this->connection->fetchAll($selectRetryRows, [], \Zend_Db::FETCH_ASSOC);
    }

    /**
     * Create a new retry entry.
     *
     * @param $binds array [
     *                       'customer_entity_id' : int
     *                       'customer_entity_email' : string
     *                       'gigya_uid': string
     *                       'direction' : self::DIRECTION_CMS2G or self::DIRECTION_G2CMS or self::DIRECTION_BOTH
     *                       'data' : array [ 'uid', 'profile', 'data' ]
     *                       'message' : string
     *                     ]
     */
    public function createRetryEntry($binds)
    {
        $binds['data'] = serialize($binds['data']);
        $binds['retry_count'] = 0;
        $binds['date'] = date('Y-m-d H:i:s', gmdate('U'));

        $this->connection->insert(
            $this->resourceConnection->getTableName('gigya_sync_retry'),
            $binds
        );

        $this->logger->debug(
            'Inserted a new row in gigya_sync_retry for Magento to Gigya retry',
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
     * @param $customerEntityId
     * @param $retryCount
     */
    protected function setRetryCount($customerEntityId, $retryCount)
    {
        $this->connection->update(
            'gigya_sync_retry',
            [ 'retry_count' => $retryCount ],
            'customer_entity_id = ' . $customerEntityId
        );
    }

    public function incrementRetryCount($customerEntityId)
    {
        $retryCount = $this->getCurrentRetryCount($customerEntityId);

        $this->setRetryCount($customerEntityId, ++$retryCount);

        $this->logger->debug(
            'Increment gigya_sync_retry.retry_count for Magento to Gigya retry',
            [
                'customer_entity_id' => $customerEntityId,
                'retry_count' => $retryCount
            ]
        );
    }

    /**
     * Delete an existing retry entry.
     *
     * @param $customerEntityId integer Customer entity id of the row to delete.
     * @param $successMessage string Message to log (info) in case of delete is successful.
     * @param $failureMessage string Message to log (critical) on case of delete is failure.
     */
    public function deleteRetryEntry(
        $customerEntityId,
        $successMessage = null,
        $failureMessage = null
    )
    {
        $retryCount = $this->getCurrentRetryCount($customerEntityId);

        if ($retryCount > -1) {
            try {
                $this->connection->delete(
                    'gigya_sync_retry',
                    'customer_entity_id = ' . $customerEntityId
                );
                if (!is_null($successMessage)) {
                    $this->logger->info(
                        $successMessage,
                        ['customer_entity_id' => $customerEntityId]
                    );
                } else {
                    $this->logger->debug(
                        'Delete row gigya_sync_retry for Magento to Gigya retry',
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
                        'Could not delete row gigya_sync_retry for Magento to Gigya retry',
                        ['customer_entity_id' => $customerEntityId]
                    );
                }
            }
        }
    }
}

<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model\Cron;


use Gigya\CmsStarterKit\user\GigyaUserFactory;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Model\GigyaAccountService;
use Gigya\GigyaIM\Model\ResourceModel\ConnectionFactory;
use Gigya\GigyaIM\Observer\SyncCustomerToGigyaObserver;
use Magento\Framework\App\ResourceConnection;
use \Magento\Framework\Event\ManagerInterface as EventManager;
use \Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * CronGigyaAccountService
 *
 * @inheritdoc
 *
 * Add a new method getForRetryByCustomerEntityId : instead of getting the Gigya data from the regular Gigya service, get them from the 'gigya_sync_retry' db table.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class CronGigyaAccountService extends GigyaAccountService {

    /** @var  ResourceConnection */
    protected $resourceConnection;

    /** @var ConnectionFactory */
    protected $connectionFactory;

    /**
     * CronGigyaAccountService constructor.
     *
     * @param GigyaMageHelper $gigyaMageHelper
     * @param EventManager $eventManager
     * @param GigyaLogger $logger
     * @param ResourceConnection $connection
     * @param ConnectionFactory $connectionFactory
     */
    public function __construct(
        GigyaMageHelper $gigyaMageHelper,
        EventManager $eventManager,
        GigyaLogger $logger,
        ResourceConnection $connection,
        ConnectionFactory $connectionFactory
    )
    {
        parent::__construct($gigyaMageHelper, $eventManager, $logger);
        $this->connectionFactory = $connectionFactory;
        $this->resourceConnection = $connection;
    }

    /**
     * @inheritdoc
     *
     * Get the Gigya user data as stored in db table 'gigya_sync_retry'
     */
    function get($uid)
    {
        $connection = $this->connectionFactory->getNewConnection();

        $selectRetryRows = $connection
            ->select()
            ->from('gigya_sync_retry')
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns([ 'data' ])
            ->where('direction = "' . SyncCustomerToGigyaObserver::DIRECTION_CMS2G . '"')
            ->where('gigya_uid = "'.$uid . '"');

        $retryRow = $connection->fetchAll($selectRetryRows, [], \Zend_Db::FETCH_ASSOC);

        return GigyaUserFactory::createGigyaUserFromArray(unserialize($retryRow[0]['data']));
    }
}
<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model\Cron;


use Gigya\CmsStarterKit\user\GigyaUserFactory;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Model\GigyaAccountService;
use Gigya\GigyaIM\Observer\SyncCustomerToGigyaObserver;
use Magento\Framework\App\ResourceConnection;
use \Magento\Framework\Model\Context;

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

    public function __construct(
        GigyaMageHelper $gigyaMageHelper,
        Context $context,
        ResourceConnection $connection
    )
    {
        parent::__construct($gigyaMageHelper, $context);

        $this->resourceConnection = $connection;
    }

    /**
     * @inheritdoc
     *
     * Get the Gigya user data as stored in db table 'gigya_sync_retry'
     *
     * @param string $uid /!\ Not the true Gigya UID : it's the customer_entity_id to retrieve the table row.
     */
    function get($uid)
    {
        $connection = $this->resourceConnection->getConnection();

        $selectRetryRows = $connection
            ->select()
            ->from('gigya_sync_retry')
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns([ 'customer_entity_id', 'retry_count' ])
            ->where('direction = "' . SyncCustomerToGigyaObserver::DIRECTION_CMS2G . '"')
            ->where('customer_entity_id = '.$uid);

        $retryRow = $connection->fetchAll($selectRetryRows, [], \Zend_Db::FETCH_ASSOC);

        return GigyaUserFactory::createGigyaUserFromArray(unserialize($retryRow['data']));
    }
}
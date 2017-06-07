<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model\Cron;


use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\CmsStarterKit\user\GigyaUserFactory;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Model\GigyaAccountService;
use Gigya\GigyaIM\Observer\SyncCustomerToGigyaObserver;
use Magento\Framework\App\ResourceConnection;
use \Magento\Framework\Event\ManagerInterface as EventManager;
use \Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * CronGigyaAccountService
 *
 * @inheritdoc
 *
 * Overrides get method : instead of getting the Gigya data from the regular Gigya service get them from the 'gigya_sync_retry' db table.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class CronGigyaAccountService extends GigyaAccountService {

    /** @var  ResourceConnection */
    protected $resourceConnection;

    /**
     * CronGigyaAccountService constructor.
     *
     * @param GigyaMageHelper $gigyaMageHelper
     * @param EventManager $eventManager
     * @param GigyaLogger $logger
     * @param ResourceConnection $connection
     */
    public function __construct(
        GigyaMageHelper $gigyaMageHelper,
        EventManager $eventManager,
        GigyaLogger $logger,
        ResourceConnection $connection
    )
    {
        parent::__construct($gigyaMageHelper, $eventManager, $logger);

        $this->resourceConnection = $connection;
    }

    /**
     * @inheritdoc
     *
     * Get the Gigya user data as stored in db table 'gigya_sync_retry'
     */
    function get($uid)
    {
        $connection = $this->resourceConnection->getConnection('gigya');

        $selectRetryRows = $connection
            ->select()
            ->from('gigya_sync_retry')
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns([ 'customer_entity_id', 'data' ])
            ->where('direction = "' . SyncCustomerToGigyaObserver::DIRECTION_CMS2G . '"')
            ->where('gigya_uid = "'.$uid . '"');

        $allRetryRows = $connection->fetchAll($selectRetryRows, [], \Zend_Db::FETCH_ASSOC);

        if (!empty($allRetryRows) && count($allRetryRows) === 1) {
            $savedGigyaData = unserialize($allRetryRows[0]['data']);
            /** @var GigyaUser $result */
            $gigyaAccountData = GigyaUserFactory::createGigyaUserFromArray($savedGigyaData);
            // Cf. cms-starter-kit : loginIDs is not mapped by createGigyaUserFromArray...
            $gigyaAccountData->setLoginIDs([
                'emails' => $savedGigyaData['data']['loginIDs']
            ]);
            return $gigyaAccountData;
        } else {
            return null;
        }
    }
}
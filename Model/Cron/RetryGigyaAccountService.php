<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model\Cron;


use Gigya\CmsStarterKit\sdk\GSApiException;
use Gigya\CmsStarterKit\user\GigyaProfile;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\CmsStarterKit\user\GigyaUserFactory;
use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Helper\RetryGigyaSyncHelper;
use Gigya\GigyaIM\Model\GigyaAccountService;
use \Magento\Framework\Event\ManagerInterface as EventManager;
use \Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * GigyaAccountService
 *
 * @inheritdoc
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class RetryGigyaAccountService extends GigyaAccountService {

    /** @var RetryGigyaSyncHelper  */
    protected $retryGigyaSyncHelper;

    public function __construct(
        GigyaMageHelper $gigyaMageHelper,
        EventManager $eventManager,
        GigyaLogger $logger,
        RetryGigyaSyncHelper $retryGigyaSyncHelper
    ) {

        parent::__construct($gigyaMageHelper, $eventManager, $logger);

        $this->retryGigyaSyncHelper = $retryGigyaSyncHelper;
    }

    /**
     * @inheritdoc
     *
     * The Gigya account is retrieved from the scheduled retry entry linked to this uid, if any.
     *
     * Will add the 'customer_entity_id' on the resulting GigyaUser.
     */
    function get($uid)
    {
        $savedGigyaData = $this->retryGigyaSyncHelper->getRetryEntries(null, $uid, true);

        $result = GigyaUserFactory::createGigyaUserFromArray(unserialize($savedGigyaData[0]['data']));
        $result->setCustomerEntityId($savedGigyaData[0]['customer_entity_id']);

        return $result;
    }
}

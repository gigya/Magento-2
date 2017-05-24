<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model;


use Gigya\CmsStarterKit\sdk\GSApiException;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use \Magento\Framework\Model\Context;
use Monolog\Logger;

/**
 * GigyaAccountService
 *
 * @inheritdoc
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class GigyaAccountService implements GigyaAccountServiceInterface {

    /**
     * Event dispatched when the Gigya data have correctly been sent to the Gigya remote service.
     */
    const EVENT_UPDATE_GIGYA_SUCCESS = 'gigya_success_sync_to_gigya';

    /**
     * Event dispatched when the Gigya data could not be sent to the Gigya remote service or when this service replies with an error (validation or other functionnal error)
     */
    const EVENT_UPDATE_GIGYA_FAILURE = 'gigya_failed_sync_to_gigya';

    /** @var  GigyaMageHelper */
    protected $gigyaMageHelper;

    /** @var \Magento\Framework\Event\ManagerInterface */
    protected $eventManager;

    /** @var  Logger */
    protected $logger;

    /**
     * GigyaAccountService constructor.
     *
     * @param GigyaMageHelper $gigyaMageHelper
     * @param Context $context
     * @param Logger $logger
     */
    public function __construct(
        GigyaMageHelper $gigyaMageHelper,
        Context $context,
        Logger $logger
    )
    {
        $this->gigyaMageHelper = $gigyaMageHelper;
        $this->eventManager = $context->getEventDispatcher();
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     *
     * Dispatch an event :
     * self::EVENT_UPDATE_GIGYA_SUCCESS
     * or self::EVENT_UPDATE_GIGYA_FAILURE
     */
    public function update($gigyaAccount)
    {
        $gigyaApiData = $this->buildEventData($gigyaAccount);

        try {

            /*throw new GSApiException("test", 1, sprintf(
                    "Test Gigya update failure => retry for uid : %s, customer_entity_id : %s customer_email : %s",
                    $gigyaApiData['uid'],
                    $gigyaAccount->getMagentoEntityId(),
                    $gigyaApiData['profile']['email']
                )
            );*/

            $this->gigyaMageHelper->updateGigyaAccount(
                $gigyaApiData['uid'],
                $gigyaApiData['profile'],
                $gigyaApiData['data']
            );
            $this->logger->debug(
                'Successful call to Gigya service api',
                $gigyaApiData
            );
            $this->eventManager->dispatch(self::EVENT_UPDATE_GIGYA_SUCCESS, [
                    'customer_entity_id' => $gigyaAccount->getMagentoEntityId(),
                    'gigya_data' => $gigyaApiData
                ]
            );
        } catch(GSApiException $e) {
            $this->logger->error(
                'Failure encountered on call to Gigya service api',
                [
                    'gigya_data' => $gigyaApiData,
                    'exception' => [
                        'code' => $e->getCode(),
                        'message' => $e->getLongMessage()
                    ]
                ]
            );
            $this->eventManager->dispatch(self::EVENT_UPDATE_GIGYA_FAILURE, [
                    'customer_entity_id' => $gigyaAccount->getMagentoEntityId(),
                    'gigya_data' => $gigyaApiData
                ]
            );
            throw $e;
        }
    }

    function get($uid)
    {
        return $this->gigyaMageHelper->getGigyaAccountDataFromUid($uid);
    }

    /**
     * Facility to build the profile data correctly formatted for the service call.
     *
     * @param GigyaUser $gigyaAccount
     * @return array
     */
    protected function getGigyaApiProfile(GigyaUser $gigyaAccount)
    {
        $profile = $gigyaAccount->getProfile();

        return [
            'email' => $profile->getEmail(),
            'firstName' => $profile->getFirstName(),
            'lastName' => $profile->getLastName()
        ];
    }

    /**
     * Facility to build the core data correctly formatted for the service call.
     *
     * @param GigyaUser $gigyaAccount
     * @return array
     */
    protected function getGigyaApiCoreData(GigyaUser $gigyaAccount)
    {
        return [
            'loginIDs' => $gigyaAccount->getLoginIDs()['emails']
        ];
    }

    /**
     * Builds the whole data correctly formatted for the service call.
     *
     * @param GigyaUser $gigyaAccount
     * @return array With entries uid, profile, data
     */
    protected function buildEventData(GigyaUser $gigyaAccount)
    {
        return [
            'uid' => $gigyaAccount->getUid(),
            'profile' => $this->getGigyaApiProfile($gigyaAccount),
            'data' => $this->getGigyaApiCoreData($gigyaAccount)
        ];
    }
}
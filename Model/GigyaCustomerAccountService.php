<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model;


use Gigya\CmsStarterKit\sdk\GSApiException;
use Gigya\GigyaIM\Api\Data\GigyaCustomerAccountInterface;
use Gigya\GigyaIM\Api\GigyaCustomerAccountServiceInterface;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use \Magento\Framework\Model\Context;
use Monolog\Logger;

/**
 * GigyaCustomerAccountService
 *
 * @inheritdoc
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class GigyaCustomerAccountService implements GigyaCustomerAccountServiceInterface
{
    const EVENT_UPDATE_GIGYA_SUCCESS = 'success_sync_to_gigya';
    const EVENT_UPDATE_GIGYA_FAILURE = 'failed_sync_to_gigya';

    /** @var  GigyaMageHelper */
    protected $gigyaMageHelper;

    /** @var \Magento\Framework\Event\ManagerInterface */
    protected $eventManager;

    /** @var  Logger */
    protected $logger;

    /**
     * GigyaCustomerAccountService constructor.
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
    public function update(GigyaCustomerAccountInterface $gigyaCustomerAccount)
    {
        $gigyaApiData = $this->buildEventData($gigyaCustomerAccount);

        try {
            $this->gigyaMageHelper->getGigyaApiHelper()->updateGigyaAccount(
                $gigyaApiData['uid'],
                $gigyaApiData['profile'],
                $gigyaApiData['data']
            );
            $this->logger->debug(
                'Successful call to Gigya service api',
                $gigyaApiData
            );
            $this->eventManager->dispatch(self::EVENT_UPDATE_GIGYA_SUCCESS, [
                    'customer_entity_id' => $gigyaCustomerAccount->getEntityId(),
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
                    'customer_entity_id' => $gigyaCustomerAccount->getEntityId(),
                    'gigya_data' => $gigyaApiData
                ]
            );
            throw $e;
        }
    }

    /**
     * Facility to build the profile data correctly formatted for the service call.
     *
     * @param GigyaCustomerAccountInterface $gigyaCustomerAccount
     * @return array
     */
    protected function getGigyaApiProfile(GigyaCustomerAccountInterface $gigyaCustomerAccount)
    {
        return [ 'email' => $gigyaCustomerAccount->getLoginEmail() ];
    }

    /**
     * Facility to build the core data correctly formatted for the service call.
     *
     * @param GigyaCustomerAccountInterface $gigyaCustomerAccount
     * @return array
     */
    protected function getGigyaApiCoreData(GigyaCustomerAccountInterface $gigyaCustomerAccount)
    {
        return [ 'loginIDs' => [ $gigyaCustomerAccount->getLoginEmail() ] ];
    }

    /**
     * Builds the whole data correctly formatted for the service call.
     *
     * @param GigyaCustomerAccountInterface $gigyaCustomerAccount
     * @return array With entries uid, profile, data
     */
    protected function buildEventData(GigyaCustomerAccountInterface $gigyaCustomerAccount)
    {
        return [
            'uid' => $gigyaCustomerAccount->getUid(),
            'profile' => $this->getGigyaApiProfile($gigyaCustomerAccount),
            'data' => $this->getGigyaApiCoreData($gigyaCustomerAccount)
        ];
    }
}
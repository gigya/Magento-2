<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Helper\RetryGigyaSyncHelper;
use Gigya\GigyaIM\Model\GigyaAccountService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * SyncCustomerToGigyaObserver
 *
 * Is triggered when a Gigya data update (synchronizing with the Gigya service) has failed or succeeded.
 *
 * If failure : @see SyncCustomerToGigyaObserver::performGigyaUpdateFailure()
 *
 * If success : @see SyncCustomerToGigyaObserver::performGigyaUpdateSuccess()
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class SyncCustomerToGigyaObserver implements ObserverInterface
{
    /** @var  GigyaLogger */
    protected $logger;

    /** @var RetryGigyaSyncHelper  */
    protected $retryGigyaSyncHelper;

    /**
     * SyncCustomerToGigyaObserver constructor.
     *
     * @param GigyaLogger $logger
     * @param RetryGigyaSyncHelper $retryGigyaSyncHelper
     */
    public function __construct(
        GigyaLogger $logger,
        RetryGigyaSyncHelper $retryGigyaSyncHelper
    )
    {
        $this->logger = $logger;

        $this->retryGigyaSyncHelper = $retryGigyaSyncHelper;
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
                $this->performGigyaUpdateFailure($observer);
                break;

            case GigyaAccountService::EVENT_UPDATE_GIGYA_SUCCESS :
                $this->performGigyaUpdateSuccess($observer);
                break;

            case AbstractGigyaAccountEnricher::EVENT_MAP_GIGYA_FROM_MAGENTO_FAILURE :
                $this->performFieldMappingFailure($observer);
                break;
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
     * @param Observer $observer
     * @return void
     */
    protected function performGigyaUpdateFailure($observer)
    {
        /** @var integer $customerEntityId */
        $customerEntityId = $observer->getData('customer_entity_id');
        /** @var string $customerEntityEmail */
        $customerEntityEmail = $observer->getData('customer_entity_email');
        /** @var array $gigyaAccountData with entries uid, profile, data */
        $gigyaAccountData = $observer->getData('gigya_data');
        /** @var string $message */
        $message = $observer->getData('message');

        $this->retryGigyaSyncHelper->scheduleRetry($customerEntityId, $customerEntityEmail, $gigyaAccountData, $message);
    }

    /**
     * If a retry row has been stored we will delete it when the a customer update has succeeded.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    protected function performGigyaUpdateSuccess($observer)
    {
        /** @var integer $customerEntityId */
        $customerEntityId = $observer->getData('customer_entity_id');

        $this->retryGigyaSyncHelper->deleteRetryEntry(
            $customerEntityId,
            'Previously failed Gigya update has now succeeded.',
            'Could not remove retry entry for Magento to Gigya update after a successful update on the same Gigya account.'
        );
    }

    /**
     * Delete the retry row, if any, if a customer update has failed due to a field mapping error.
     *
     * @param $observer
     */
    protected function performFieldMappingFailure($observer)
    {
        /** @var integer $customerEntityId */
        $customerEntityId = $observer->getData('customer_entity_id');

        $this->retryGigyaSyncHelper->deleteRetryEntry(
            $customerEntityId,
            'Previously failed Gigya update now fails due to field mapping. No automatic retry will be performed on it.',
            'Could not remove retry entry for Magento to Gigya update after a field mapping error on the same customer.'
        );
    }
}
<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Helper\GigyaSyncRetryHelper;
use Gigya\GigyaIM\Model\GigyaAccountService;
use Gigya\GigyaIM\Model\ResourceModel\ConnectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State as AppState;
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
    /** @var  GigyaMageHelper */
    protected $gigyaMageHelper;

    /** @var  AppState */
    protected $appState;

    /** @var  GigyaLogger */
    protected $logger;

    /** @var int */
    private $maxGigyaUpdateRetryCount;

    /** @var  GigyaSyncRetryHelper */
    protected $gigyaSyncRetryHelper;

    /**
     * SyncCustomerToGigyaObserver constructor.
     *
     * @param GigyaMageHelper $gigyaMageHelper
     * @param AppState $state
     * @param GigyaLogger $logger
     * @param GigyaSyncRetryHelper $gigyaSyncRetryHelper
     */
    public function __construct(
        GigyaMageHelper $gigyaMageHelper,
        AppState $state,
        GigyaLogger $logger,
        GigyaSyncRetryHelper $gigyaSyncRetryHelper
    )
    {
        $this->gigyaMageHelper = $gigyaMageHelper;
        $this->appState = $state;
        $this->logger = $logger;

        $this->maxGigyaUpdateRetryCount = $this->gigyaMageHelper->getMaxRetryCountForGigyaUpdate();
        $this->gigyaSyncRetryHelper = $gigyaSyncRetryHelper;
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

        $binds = [
            'customer_entity_id' => $customerEntityId,
            'customer_entity_email' => $customerEntityEmail,
            'gigya_uid' => $gigyaAccountData['uid'],
            'direction' => GigyaSyncRetryHelper::DIRECTION_CMS2G,
            'data' => $gigyaAccountData,
            'message' => $message != null ? (strlen($message) > 255 ? substr($message, 255) : $message) : null
        ];

        try {
            $retryCount = $this->gigyaSyncRetryHelper->getCurrentRetryCount($customerEntityId);

            if ($retryCount == -1) {
                $this->gigyaSyncRetryHelper->createRetryEntry($binds);
            } else {
                // If failure after an automatic update retry by the cron we increment the retry count
                if ($this->appState->getAreaCode() == Area::AREA_CRONTAB) {
                    if ($retryCount == $this->maxGigyaUpdateRetryCount) {
                        $this->logger->warning(
                            sprintf(
                                'Maximum retry attempts for Magento to Gigya retry has been reached (%d). Retry is now unscheduled.',
                                $this->maxGigyaUpdateRetryCount
                            ),
                            [
                                'customer_entity_id' => $customerEntityId,
                                'customer_entity_email' => $customerEntityEmail,
                                'gigya_data' => $gigyaAccountData,
                                'message' => $message
                            ]
                        );

                        $this->gigyaSyncRetryHelper->deleteRetryEntry($customerEntityId);
                    } else {
                        $this->gigyaSyncRetryHelper->incrementRetryCount($customerEntityId);
                    }
                } else { // Failure not in the automatic cron update retry context : set the retry count to 0
                    $this->gigyaSyncRetryHelper->resetRetryCount($customerEntityId);
                }

                $this->gigyaSyncRetryHelper->commit();
            }
        } catch(\Exception $e) {

            $this->logger->critical(
                'Could not log retry entry for Magento to Gigya update. No automatic retry will be performed on it.',
                [
                    'exception' => $e,
                    'customer_entity_id' => $customerEntityId,
                    'customer_entity_email' => $customerEntityEmail,
                    'gigya_data' => $gigyaAccountData
                ]
            );

            $this->gigyaSyncRetryHelper->rollBack();
        }
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

        $this->gigyaSyncRetryHelper->deleteRetryEntry(
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

        $this->gigyaSyncRetryHelper->deleteRetryEntry(
            $customerEntityId,
            'Previously failed Gigya update now fails due to field mapping. No automatic retry will be performed on it.',
            'Could not remove retry entry for Magento to Gigya update after a field mapping error on the same customer.'
        );
    }
}
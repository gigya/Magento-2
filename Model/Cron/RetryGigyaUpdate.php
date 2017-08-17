<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model\Cron;

use Gigya\GigyaIM\Helper\RetryGigyaSyncHelper;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * RetryGigyaUpdate
 *
 * Fetch the retry entries scheduled to perform the Gigya profile and Magento Customer update retries.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class RetryGigyaUpdate
{
    /** @var  GigyaLogger */
    protected $logger;

    /** @var  RetryGigyaSyncHelper */
    protected $retryGigyaSyncHelper;

    protected $customerRepository;

    /**
     * RetryGigyaUpdate constructor.
     *
     * @param GigyaLogger $logger
     * @param RetryGigyaSyncHelper $retryGigyaSyncHelper
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        GigyaLogger $logger,
        RetryGigyaSyncHelper $retryGigyaSyncHelper,
        CustomerRepositoryInterface $customerRepository
    )
    {
        $this->logger = $logger;
        $this->retryGigyaSyncHelper = $retryGigyaSyncHelper;
        $this->customerRepository = $customerRepository;
    }

    /**
     * For all scheduled retry entries will perform a Gigya & Magento update on the corresponding accounts & Customer entities.
     *
     * @param \Magento\Cron\Model\Schedule $schedule
     */
    public function execute(\Magento\Cron\Model\Schedule $schedule)
    {
        $allRetriesRow = $this->retryGigyaSyncHelper->getRetryEntries(null);

        foreach($allRetriesRow as $retryRow) {
            $customerEntityId = $retryRow['customer_entity_id'];
            $customerEntityEmail = $retryRow['customer_entity_email'];
            $gigyaUid = $retryRow['gigya_uid'];
            $retryCount = 1 + $retryRow['retry_count'];
            /** @var CustomerInterface $customer */
            $customer = $this->customerRepository->getById($customerEntityId);
            try {
                $this->customerRepository->save($customer);
            } catch (\Exception $e) {
                $message = $e->getMessage();
                $message = $message != null ? (strlen($message) > 255 ? substr($message, 0, 255).' ...': $message) : null;
                $this->logger->warning('Retry update Gigya failed.',
                    [
                        'customer_entity_id' => $customerEntityId,
                        'customer_entity_email' => $customerEntityEmail,
                        'gigya_uid' => $gigyaUid,
                        'retry_count' => $retryCount,
                        $message
                    ]
                );
            }
        }
    }
}

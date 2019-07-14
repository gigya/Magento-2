<?php

namespace Gigya\GigyaIM\Model\Cron;

use Gigya\GigyaIM\Helper\RetryGigyaSyncHelper;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use \Magento\Framework\Event\ManagerInterface as EventManager;

/**
 * RetryGigyaUpdate
 *
 * Fetch the retry entries scheduled to perform the Gigya profile and Magento Customer update retries.
 */
class RetryGigyaUpdate
{
    /** @var  GigyaLogger */
    protected $logger;

    /** @var  RetryGigyaSyncHelper */
    protected $retryGigyaSyncHelper;

    protected $customerRepository;

    /** @var GigyaConfig */
	protected $config;

	/** @var EventManager */
	protected $eventManager;

	/**
	 * RetryGigyaUpdate constructor.
	 *
	 * @param GigyaLogger                 $logger
	 * @param RetryGigyaSyncHelper        $retryGigyaSyncHelper
	 * @param CustomerRepositoryInterface $customerRepository
	 * @param GigyaConfig                 $config
	 * @param EventManager                $eventManager
	 */
    public function __construct(
        GigyaLogger $logger,
        RetryGigyaSyncHelper $retryGigyaSyncHelper,
        CustomerRepositoryInterface $customerRepository,
		GigyaConfig $config,
		EventManager $eventManager
    )
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->retryGigyaSyncHelper = $retryGigyaSyncHelper;
        $this->customerRepository = $customerRepository;
        $this->eventManager = $eventManager;
    }

	/**
	 * For all scheduled retry entries will perform a Gigya & Magento update on the corresponding accounts & Customer entities.
	 *
	 * @param \Magento\Cron\Model\Schedule $schedule
	 *
	 * @throws \Gigya\GigyaIM\Exception\RetryGigyaException
	 * @throws \Magento\Framework\Exception\LocalizedException
	 * @throws \Magento\Framework\Exception\NoSuchEntityException
	 */
    public function execute(\Magento\Cron\Model\Schedule $schedule)
    {
		if ($this->config->isGigyaEnabled())
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
					$this->eventManager->dispatch('gigya_fieldmapping_retry_before_save', array('customer' => $customer));
					$this->customerRepository->save($customer);
					$this->eventManager->dispatch('gigya_fieldmapping_retry_after_save', array('customer' => $customer));
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
}

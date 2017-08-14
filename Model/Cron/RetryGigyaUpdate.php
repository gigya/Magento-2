<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Model\Cron;

use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\CmsStarterKit\user\GigyaUserFactory;
use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncRetryHelper;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * RetryGigyaUpdate
 *
 * Fetch the retry entries scheduled to perform the Gigya update retries.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class RetryGigyaUpdate
{
    /** @var  GigyaLogger */
    protected $logger;

    /** @var  GigyaAccountRepositoryInterface */
    protected $gigyaAccountRepository;

    /** @var  GigyaSyncRetryHelper */
    protected $gigyaSyncRetryHelper;

    /**
     * RetryGigyaUpdate constructor.
     *
     * @param GigyaLogger $logger
     * @param GigyaAccountRepositoryInterface $gigyaAccountRepository
     * @param GigyaSyncRetryHelper $gigyaSyncRetryHelper
     */
    public function __construct(
        GigyaLogger $logger,
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncRetryHelper $gigyaSyncRetryHelper
    )
    {
        $this->logger = $logger;
        $this->gigyaAccountRepository = $gigyaAccountRepository;
        $this->gigyaSyncRetryHelper = $gigyaSyncRetryHelper;
    }

    /**
     * @param \Magento\Cron\Model\Schedule $schedule
     */
    public function execute(\Magento\Cron\Model\Schedule $schedule)
    {
        $allRetriesRow = $this->gigyaSyncRetryHelper->getRetryEntries(GigyaSyncRetryHelper::DIRECTION_CMS2G);

        foreach($allRetriesRow as $retryRow) {
            $customerEntityId = $retryRow['customer_entity_id'];
            $customerEntityEmail = $retryRow['customer_entity_email'];
            $retryCount = 1 + $retryRow['retry_count'];
            try {

                // TODO : gigya data v0 (cf Service get)

                $savedGigyaData = unserialize($retryRow['data']);
                /** @var GigyaUser $result */
                $gigyaAccountData = GigyaUserFactory::createGigyaUserFromArray($savedGigyaData);
                $gigyaAccountData->setCustomerEntityId($retryRow['customer_entity_id']);
                $customerEntityEmail = $retryRow['customer_entity_email'];
                $gigyaAccountData->setCustomerEntityEmail($customerEntityEmail);
                $gigyaAccountData->setLoginIDs([
                    'emails' => [ $customerEntityEmail ]
                ]);

                $this->gigyaAccountRepository->update($gigyaAccountData);
            } catch (\Exception $e) {
                $this->logger->warning('Retry update Gigya failed.',
                    [
                        'customer_entity_id' => $customerEntityId,
                        'customer_entity_email' => $customerEntityEmail,
                        'retry_count' => $retryCount,
                        'message' => $e->getMessage()
                    ]
                );
            }
        }
    }
}

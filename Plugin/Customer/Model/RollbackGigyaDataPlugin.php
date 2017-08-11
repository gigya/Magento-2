<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Plugin\Customer\Model;

use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Exception\GigyaMagentoCustomerSaveException;
use Gigya\GigyaIM\Helper\RetryGigyaSyncHelper;
use Gigya\GigyaIM\Model\Cron\RetryGigyaUpdate;
use Gigya\GigyaIM\Model\GigyaAccountService;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * RollbackGigyaDataPlugin
 *
 * Will trigger the Gigya rollback if a Magento customer save has failed, and schedule a new retry entry (@see RetryGigyaUpdate)
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class RollbackGigyaDataPlugin
{
    /** @var  GigyaAccountServiceInterface */
    protected $gigyaAccountService;

    /** @var  RetryGigyaSyncHelper */
    protected $retryGigyaSyncHelper;

    /**
     * RollbackGigyaDataPlugin constructor.
     *
     * @param GigyaAccountServiceInterface $gigyaAccountService
     * @param RetryGigyaSyncHelper $retryGigyaSyncHelper
     */
    public function __construct(
        GigyaAccountServiceInterface $gigyaAccountService,
        RetryGigyaSyncHelper $retryGigyaSyncHelper
    ) {
        $this->gigyaAccountService = $gigyaAccountService;
        $this->retryGigyaSyncHelper = $retryGigyaSyncHelper;
    }

    /**
     * If the Magento customer save fails :
     * . we have to roll back the Gigya account because it's been updated just before the save.
     * . retry entry is scheduled.
     *
     * @param CustomerRepositoryInterface $subject
     * @param \Closure $proceed
     * @param CustomerInterface $customer
     * @return CustomerInterface
     * @throws GigyaMagentoCustomerSaveException
     */
    public function aroundSave(
        CustomerRepositoryInterface $subject,
        \Closure $proceed,
        CustomerInterface $customer
    )
    {
       try {
           $result = $proceed($customer);
           $this->retryGigyaSyncHelper->deleteRetryEntry(
               $customer->getId(),
               'Previously failed Magento Customer entity update has now succeeded.',
               'Could not remove retry entry for Magento update after a successful update on the same Magento Customer entity.'
           );
           return $result;
       } catch(\Exception $e) {
           $guid = null;
           if($customer->getCustomAttribute('gigya_uid'))
           {
               $guid = $customer->getCustomAttribute('gigya_uid')->getValue();
           }
           if(!is_null($guid))
           {
               /** @var GigyaUser $previousGigyaAccount */
               $previousGigyaAccount = $this->gigyaAccountService->getLatestUpdated($guid);
               if (!is_null($previousGigyaAccount)) {
                   $this->gigyaAccountService->rollback($guid);
                   $gigyaAccountData = GigyaAccountService::getGigyaApiAccountData($previousGigyaAccount);
                   $this->retryGigyaSyncHelper->scheduleRetry(
                       $customer->getId(),
                       $customer->getEmail(),
                       $gigyaAccountData,
                       'Failure encountered on Magento Customer entity save : ' . $e->getMessage()
                   );
               }
           }

            throw new GigyaMagentoCustomerSaveException($e);
       }
    }
}
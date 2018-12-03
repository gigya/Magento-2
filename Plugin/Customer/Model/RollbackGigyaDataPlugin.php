<?php

namespace Gigya\GigyaIM\Plugin\Customer\Model;

use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Exception\GigyaMagentoCustomerSaveException;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Helper\RetryGigyaSyncHelper;
use Gigya\GigyaIM\Model\GigyaAccountService;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * RollbackGigyaDataPlugin
 *
 * Will perform the Gigya rollback if a Magento customer save has failed, and manage the retry entries.
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
	 * If the Magento customer save fails AND is to be synchronized to Gigya (*) we have to roll back the Gigya account because it could have been updated just before the save.
	 *
	 * (*) we also save the Magento customer when it's loaded in backend, after being enriched with the current data from Gigya : in this case we do not want to sync back the customer to Gigya.
	 *
	 * @param CustomerRepositoryInterface $subject
	 * @param \Closure $proceed
	 * @param CustomerInterface $customer
	 *
	 * @return CustomerInterface
	 *
	 * @throws GigyaMagentoCustomerSaveException
	 * @throws \Gigya\GigyaIM\Exception\RetryGigyaException
	 */
    public function aroundSave(
        CustomerRepositoryInterface $subject,
        \Closure $proceed,
        CustomerInterface $customer
    )
    {
        $result = null;

        try {

            $result = $proceed($customer);

            if (!$this->retryGigyaSyncHelper->isCustomerIdExcludedFromSync($customer->getId(), GigyaSyncHelper::DIR_CMS2G)) {
                $this->retryGigyaSyncHelper->deleteRetryEntry(
                    RetryGigyaSyncHelper::ORIGIN_CMS,
                    $customer->getId(),
                    'Previously failed Magento Customer entity update has now succeeded.',
                    'Could not remove retry entry for Magento update after a successful update on the same Magento Customer entity.'
                );
            }
        } catch (\Exception $e) {
            $uid = $customer->getCustomAttribute('gigya_uid') != null ? $customer->getCustomAttribute('gigya_uid')->getValue() : null;
            if (!is_null($uid)) {
                if (!$this->retryGigyaSyncHelper->isCustomerIdExcludedFromSync($customer->getId(), GigyaSyncHelper::DIR_CMS2G)) {
                    $rolledBackGigyaAccount = $this->gigyaAccountService->rollback($uid);
                    if (!is_null($rolledBackGigyaAccount)) {
                        $this->retryGigyaSyncHelper->scheduleRetry(
                            RetryGigyaSyncHelper::ORIGIN_CMS,
                            $customer->getId(),
                            $customer->getEmail(),
                            GigyaAccountService::getGigyaApiAccountData($rolledBackGigyaAccount),
                            'Failure encountered on Magento Customer entity save : ' . $e->getMessage()
                        );
                    }
                }
            }

            throw new GigyaMagentoCustomerSaveException($e);
        }

        return $result;
    }
}
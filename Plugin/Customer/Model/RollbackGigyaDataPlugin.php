<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Plugin\Customer\Model;

use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Exception\GigyaMagentoCustomerSaveException;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * RollbackGigyaDataPlugin
 *
 * Will trigger the Gigya rollback if a Magento customer save has failed.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class RollbackGigyaDataPlugin
{
    /** @var  GigyaAccountServiceInterface */
    protected $gigyaAccountService;

    /** @var  GigyaSyncHelper */
    protected $gigyaSyncHelper;

    /**
     * RollbackGigyaDataPlugin constructor.
     *
     * @param GigyaAccountServiceInterface $gigyaAccountService
     * @param GigyaSyncHelper $gigyaSyncHelper
     */
    public function __construct(
        GigyaAccountServiceInterface $gigyaAccountService,
        GigyaSyncHelper $gigyaSyncHelper
    ) {
        $this->gigyaAccountService = $gigyaAccountService;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
    }

    /**
     * If the Magento customer save fails we have to roll back the Gigya account because it's been updated just before the save.
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
           return $proceed($customer);
       } catch(\Exception $e) {
            $this->gigyaAccountService->rollback($customer->getCustomAttribute('gigya_uid')->getValue());
            throw new GigyaMagentoCustomerSaveException($e);
       }
    }
}
<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;

/**
 * BackendMagentoCustomerEnricher
 *
 * @inheritdoc
 *
 * The Gigya data will be fetched from the Gigya's service.
 *
 * @see GigyaAccountServiceInterface
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class BackendMagentoCustomerEnricher extends AbstractMagentoCustomerEnricher
{
    /** @var  GigyaAccountServiceInterface */
    protected $gigyaAccountService;

    /** @var  GigyaSyncHelper */
    protected $gigyaSyncHelper;

    /** @var  CustomerRepositoryInterface */
    protected $customerRepository;

    /**
     * BackendMagentoCustomerEnricher constructor.
     *
     * @param GigyaAccountServiceInterface $gigyaAccountService
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        GigyaAccountServiceInterface $gigyaAccountService,
        GigyaSyncHelper $gigyaSyncHelper,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->gigyaAccountService = $gigyaAccountService;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @inheritdoc
     */
    protected function enrichMagentoCustomer($magentoCustomer)
    {
        $gigyaAccountData = $this->gigyaAccountService->get($magentoCustomer->getGigyaUid());
        $gigyaAccountLoggingEmail = $this->gigyaSyncHelper->getMagentoCustomerAndLoggingEmail($gigyaAccountData)['logging_email'];
        $this->gigyaSyncHelper->updateMagentoCustomerWithGygiaAccount($magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail);
        $this->customerRepository->save($magentoCustomer->getDataModel());
    }
}
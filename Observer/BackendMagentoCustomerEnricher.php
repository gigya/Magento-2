<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\ResourceModel\CustomerRepository;

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
     * @param CustomerRegistry $customerRegistry
     */
    public function __construct(
        GigyaAccountServiceInterface $gigyaAccountService,
        GigyaSyncHelper $gigyaSyncHelper,
        CustomerRepositoryInterface $customerRepository,
        CustomerRegistry $customerRegistry
    ) {
        parent::__construct($customerRegistry);

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
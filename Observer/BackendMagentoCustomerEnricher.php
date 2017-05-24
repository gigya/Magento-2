<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;

/**
 * BackendMagentoCustomerEnricher
 *
 * @inheritdoc
 *
 * Overrides the enrichment function : the Magento customer entity is saved in database when it's been enriched.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class BackendMagentoCustomerEnricher extends AbstractMagentoCustomerEnricher
{
    /** @var  CustomerRepositoryInterface */
    protected $customerRepository;

    /**
     * BackendMagentoCustomerEnricher constructor.
     *
     * @param GigyaAccountRepositoryInterface $gigyaAccountRepository
     * @param GigyaSyncHelper $gigyaSyncHelper
     */
    public function __construct(
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        CustomerRepositoryInterface $customerRepository
    ) {
        parent::__construct($gigyaAccountRepository, $gigyaSyncHelper);

        $this->customerRepository = $customerRepository;
    }

    /**
     * @inheritdoc
     *
     * Once the customer is enriched it's saved.
     */
    protected function enrichMagentoCustomer($magentoCustomer)
    {
        parent::enrichMagentoCustomer($magentoCustomer);

        $this->customerRepository->save($magentoCustomer->getDataModel());
    }
}
<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Psr\Log\LoggerInterface;

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
     * @param ManagerInterface $eventDispatcher
     * @param LoggerInterface $logger
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        ManagerInterface $eventDispatcher,
        LoggerInterface $logger,
        CustomerRepositoryInterface $customerRepository
    ) {
        parent::__construct($gigyaAccountRepository, $gigyaSyncHelper, $eventDispatcher, $logger);

        $this->customerRepository = $customerRepository;
    }

    /**
     * @inheritdoc
     *
     * For backend we shall cancel the update on third party code exception
     *
     * @throws \Exception
     */
    protected function processEventPostSyncFromGigyaException(
        $e,
        $magentoCustomer,
        $gigyaAccountData,
        $gigyaAccountLoggingEmail
    ) {
        parent::processEventPostSyncFromGigyaException($e, $magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail);

        throw $e;
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
<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use \Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Message\ManagerInterface as MessageManager;
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

    /** @var  MessageManager */
    protected $messageManager;

    /**
     * BackendMagentoCustomerEnricher constructor.
     *
     * @param GigyaAccountRepositoryInterface $gigyaAccountRepository
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param EventManager $eventDispatcher
     * @param LoggerInterface $logger
     * @param CustomerRepositoryInterface $customerRepository
     * @param MessageManager $messageManager
     */
    public function __construct(
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        EventManager $eventDispatcher,
        LoggerInterface $logger,
        CustomerRepositoryInterface $customerRepository,
        MessageManager $messageManager
    ) {
        parent::__construct($gigyaAccountRepository, $gigyaSyncHelper, $eventDispatcher, $logger);

        $this->customerRepository = $customerRepository;
        $this->messageManager = $messageManager;
    }

    /**
     * @inheritdoc
     *
     * Display a warning. The exception is muted so that the Magento customer entity will be saved with the Gigya required fields.
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

        $this->messageManager->addWarningMessage("Error sync data from Gigya , User profile didn’t update.Please verify mapping fields between Gigya and Magento. " . $e->getMessage());
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
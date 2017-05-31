<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use \Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Psr\Log\LoggerInterface;

/**
 * BackendGigyaAccountEnricher
 *
 * @inheritdoc
 *
 * Overrides the behaviour in case of exception during the fields mapping : the enrichment must be canceled.
 *
 * @author      vlemaire <info@x2i.fr>
 */
class BackendGigyaAccountEnricher extends AbstractGigyaAccountEnricher
{
    /** @var  MessageManager */
    protected $messageManager;

    /**
     * BackendGigyaAccountEnricher constructor.
     *
     * @param GigyaAccountRepositoryInterface $gigyaAccountRepository
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param EventManager $eventDispatcher
     * @param LoggerInterface $logger
     * @param MessageManager $messageManager
     */
    public function __construct(
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        EventManager $eventDispatcher,
        LoggerInterface $logger,
        MessageManager $messageManager
    ) {
        parent::__construct($gigyaAccountRepository, $gigyaSyncHelper, $eventDispatcher, $logger);

        $this->messageManager = $messageManager;
    }

    /**
     * @inheritdoc
     *
     * Display a warning and returns false to prevent enrichment process to go on : we want to cancel it.
     *
     * @return false
     */
    protected function processEventMapGigyaFromMagentoException($e, $magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail) {

        parent::processEventMapGigyaFromMagentoException($e, $magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail);

        $this->messageManager->addWarningMessage("Error sync data to Gigya , User profile didn’t update.Please verify mapping fields between Gigya and Magento. " . $e->getMessage());

        return false;
    }
}
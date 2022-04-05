<?php

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Model\FieldMapping\GigyaFromMagento;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResourceModel;
use Magento\Customer\Model\CustomerFactory;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Gigya\GigyaIM\Model\Config as GigyaConfig;

/**
 * BackendGigyaAccountEnricher
 *
 * @inheritdoc
 *
 * Overrides the behaviour in case of exception during the field mapping : the enrichment must be canceled.
 *
 * @author      vlemaire <info@x2i.fr>
 */
class BackendGigyaAccountEnricher extends AbstractGigyaAccountEnricher
{
    /** @var  MessageManager */
    protected $messageManager;

    /** @var CustomerResourceModel */
    protected $customerResourceModel;

    /** @var CustomerFactory */
    protected $customerFactory;

    /**
     * @param GigyaAccountRepositoryInterface $gigyaAccountRepository
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param EventManager $eventDispatcher
     * @param GigyaLogger $logger
     * @param MessageManager $messageManager
     * @param GigyaFromMagento $gigyaFromMagento
     * @param GigyaConfig $config
     * @param EnricherCustomerRegistry $enricherCustomerRegistry
     * @param CustomerResourceModel $customerResourceModel
     * @param CustomerFactory $customerFactory
     */
    public function __construct(
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        EventManager $eventDispatcher,
        GigyaLogger $logger,
        MessageManager $messageManager,
        GigyaFromMagento $gigyaFromMagento,
		GigyaConfig $config,
        EnricherCustomerRegistry $enricherCustomerRegistry,
        CustomerResourceModel $customerResourceModel,
        CustomerFactory $customerFactory
    ) {
        parent::__construct(
            $gigyaAccountRepository,
            $gigyaSyncHelper,
            $eventDispatcher,
            $logger,
            $gigyaFromMagento,
            $config,
            $enricherCustomerRegistry,
            $customerResourceModel,
            $customerFactory
        );

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

        $this->messageManager->addWarningMessage("Error on synchronizing data to Gigya. User profile didn't update. Please verify mapping fields between Gigya and Magento.");

        return false;
    }
}

<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Exception\GigyaMagentoCustomerSaveException;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Model\FieldMapping\GigyaToMagento;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Event\ManagerInterface;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * BackendMagentoCustomerEnricher
 *
 * @inheritdoc
 *
 * Backend enrichement of Magento customer with Gigya data happens on customer page detail loading.
 *
 * This subclass is here just to mute any GigyaMagentoCustomerSaveException that could be thrown during the enrichment, at the moment of the enriched customer is saved.
 * The exception is muted because the goal is before all to display the latest Gigya data, even if it's not persisted in Magento database.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class BackendMagentoCustomerEnricher extends AbstractMagentoCustomerEnricher
{
    /** @var CustomerRegistry  */
    protected $customerRegistry;

    /**
     * BackendMagentoCustomerEnricher constructor.
     *
     * @param CustomerRepositoryInterface $customerRepository
     * @param GigyaAccountRepositoryInterface $gigyaAccountRepository
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param ManagerInterface $eventDispatcher
     * @param GigyaLogger $logger
     * @param CustomerRegistry $customerRegistry
     * @param GigyaToMagento $gigyaToMagentoMapper
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        ManagerInterface $eventDispatcher,
        GigyaLogger $logger,
        CustomerRegistry $customerRegistry,
        GigyaToMagento $gigyaToMagentoMapper
    ) {
        parent::__construct(
            $customerRepository,
            $gigyaAccountRepository,
            $gigyaSyncHelper,
            $eventDispatcher,
            $logger,
            $gigyaToMagentoMapper
        );

        $this->customerRegistry = $customerRegistry;
    }

    /**
     * @inheritdoc
     *
     * If GigyaMagentoCustomerSaveException is caught it's muted. Any other exception is not muted.
     */
    public function saveMagentoCustomer($magentoCustomer) {

        try {
            parent::saveMagentoCustomer($magentoCustomer);
        } catch(GigyaMagentoCustomerSaveException $e) {
            $magentoCustomer->setGigyaAccountEnriched(false);
            $this->customerRegistry->push($magentoCustomer);
        }
    }
}
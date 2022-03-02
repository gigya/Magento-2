<?php

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Model\FieldMapping\GigyaToMagento;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Event\ManagerInterface;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Framework\App\Action\Context;

/**
 * BackendMagentoCustomerEnricher
 *
 * @inheritdoc
 *
 * Backend enrichment of Magento customer with Gigya data happens on customer page detail loading.
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

    /** @var Context */
    protected $context;

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
     * @param EnricherCustomerRegistry $enricherCustomerRegistry
     * @param Context $context
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        ManagerInterface $eventDispatcher,
        GigyaLogger $logger,
        CustomerRegistry $customerRegistry,
        GigyaToMagento $gigyaToMagentoMapper,
        EnricherCustomerRegistry $enricherCustomerRegistry,
        Context $context
    ) {
        parent::__construct(
            $customerRepository,
            $gigyaAccountRepository,
            $gigyaSyncHelper,
            $eventDispatcher,
            $logger,
            $gigyaToMagentoMapper,
            $enricherCustomerRegistry
        );

        $this->customerRegistry = $customerRegistry;
        $this->context = $context;
    }

    /**
     * @inheritdoc
     *
	 * If GigyaMagentoCustomerSaveException is caught it's muted. Any other exception is not muted.
	 */
	public function saveMagentoCustomer($magentoCustomer)
	{
		try {
			parent::saveMagentoCustomer($magentoCustomer);
		} catch (\Exception $e) {
			$magentoCustomer->setGigyaAccountEnriched(false);
			$this->customerRegistry->push($magentoCustomer);
		}
	}

    public function shallEnrichMagentoCustomerWithGigyaAccount($magentoCustomer, $event, $final = true)
    {
        $result = parent::shallEnrichMagentoCustomerWithGigyaAccount($magentoCustomer, $event, false);

        if ($result === true) {
            $actionName = $this->context->getRequest()->getActionName();
            $moduleName = $this->context->getRequest()->getModuleName();
            $controllerName = $this->context->getRequest()->getControllerName();
            $route = "{$moduleName}_{$controllerName}_{$actionName}";

            $candidateRoutes = [
                'customer_index_edit' => ['customer_load_after'],
                'customer_index_save' => ['customer_save_commit_after']
            ];

            if (in_array($route, array_keys($candidateRoutes)) === false) {
                $this->logger->debug("No, route {$route} it is not a candidate for enrichment");
                $result = false;
            } elseif (in_array($event, $candidateRoutes[$route]) === false) {
                $this->logger->debug("No, route {$route} it is not allowed for event {$event}");
                $result = false;
            }
        }

        if ($result === true && $final === true) {
            $this->logger->debug("Yes, enrich Magento customer with Gigya data");
        }

        return $result;
    }
}
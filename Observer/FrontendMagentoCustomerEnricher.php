<?php

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Model\FieldMapping\GigyaToMagento;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\Context;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * FrontendMagentoCustomerEnricher
 *
 * @inheritdoc
 *
 * Overrides the check for knowing if a Magento customer shall be enriched : it's also depending on the request's action name.
 * @see FrontendMagentoCustomerEnricher::shallEnrichMagentoCustomerWithGigyaAccount()
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class FrontendMagentoCustomerEnricher extends AbstractMagentoCustomerEnricher
{
    /** @var Context */
    protected $context;

	/**
	 * FrontendMagentoCustomerEnricher constructor.
	 *
	 * @param CustomerRepositoryInterface $customerRepository
	 * @param GigyaAccountRepositoryInterface $gigyaAccountRepository
	 * @param GigyaSyncHelper $gigyaSyncHelper
	 * @param GigyaLogger $logger
	 * @param Context $context
	 * @param GigyaToMagento $gigyaToMagentoMapper
     * @param EnricherCustomerRegistry $enricherCustomerRegistry
	 */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        GigyaLogger $logger,
        Context $context,
        GigyaToMagento $gigyaToMagentoMapper,
        EnricherCustomerRegistry $enricherCustomerRegistry
    ) {
        parent::__construct(
            $customerRepository,
            $gigyaAccountRepository,
            $gigyaSyncHelper,
            $context->getEventManager(),
            $logger,
            $gigyaToMagentoMapper,
            $enricherCustomerRegistry
        );

        $this->context = $context;
    }

    /**
     * @inheritdoc
     *
     * Add a check on the request's action name : update shall be performed only if we are going to login, create or update an account.
     */
    public function shallEnrichMagentoCustomerWithGigyaAccount($magentoCustomer, $event, $final = true)
    {
        $result = parent::shallEnrichMagentoCustomerWithGigyaAccount($magentoCustomer, $event, false);

        if ($result === true) {
            $actionName = $this->context->getRequest()->getActionName();
            $moduleName = $this->context->getRequest()->getModuleName();
            $controllerName = $this->context->getRequest()->getControllerName();
            $route = "{$moduleName}_{$controllerName}_{$actionName}";

            $candidateRoutes = [
                'customer_account_loginPost',
                'customer_account_createPost',
                'customer_account_editPost',
                'customer_address_edit'
            ];

            if (in_array($route, $candidateRoutes) === false) {
                $this->logger->debug("No, route {$route} it is not a candidate for enrichment");
                $result = false;
            }
        }

        if ($result === true && $final === true) {
            $this->logger->debug("Yes, enrich Magento customer with Gigya data");
        }

        return $result;
    }
}
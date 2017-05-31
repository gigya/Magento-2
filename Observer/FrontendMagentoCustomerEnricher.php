<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\Context;
use Psr\Log\LoggerInterface;

/**
 * FrontendMagentoCustomerEnricher
 *
 * @inheritdoc
 *
 * Overrides the check for knowing if a Magento customer shall be enriched : it's also depending on the request's action name.
 * @see FrontendMagentoCustomerEnricher::shallUpdateMagentoCustomerWithGigyaAccount()
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
     * @param LoggerInterface $logger
     * @param Context $context
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        LoggerInterface $logger,
        Context $context
    ) {
        parent::__construct($customerRepository, $gigyaAccountRepository, $gigyaSyncHelper, $context->getEventManager(), $logger);

        $this->context = $context;
    }

    /**
     * @inheritdoc
     *
     * Add a check on the request's action name : update shall be performed only if we are going to login, create or update an account.
     */
    public function shallUpdateMagentoCustomerWithGigyaAccount($magentoCustomer)
    {
        $actionName = $this->context->getRequest()->getActionName();

        $result = $actionName == 'loginPost'
            || $actionName == 'createPost'
            || $actionName == 'editPost';

        return $result && parent::shallUpdateMagentoCustomerWithGigyaAccount($magentoCustomer);
    }
}
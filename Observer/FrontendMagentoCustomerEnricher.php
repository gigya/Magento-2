<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Event\ManagerInterface;

/**
 * FrontendMagentoCustomerEnricher
 *
 * @inheritdoc
 *
 * Overrides the check for knowing if a Magento customer shall be enriched : it's depending on the request's action name.
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
     * @param GigyaAccountRepositoryInterface $gigyaAccountRepository
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param ManagerInterface $eventDispatcher
     * @param Context $context
     */
    public function __construct(
        GigyaAccountRepositoryInterface $gigyaAccountRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        ManagerInterface $eventDispatcher,
        Context $context
    ) {
        parent::__construct($gigyaAccountRepository, $gigyaSyncHelper, $eventDispatcher);

        $this->context = $context;
    }

    /**
     * @inheritdoc
     *
     * Add a check on the request's action name : update shall be performed only if we are going to login, create or update an account.
     */
    public function shallUpdateMagentoCustomerWithGigyaAccount($magentoCustomer)
    {
        $result = parent::shallUpdateMagentoCustomerWithGigyaAccount($magentoCustomer);

        if ($result) {
            $actionName = $this->context->getRequest()->getActionName();

            $result = $actionName == 'loginPost'
                || $actionName == 'createPost'
                || $actionName == 'editPost';
        }

        return $result;
    }
}
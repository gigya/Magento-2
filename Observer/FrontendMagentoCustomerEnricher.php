<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Api\GigyaAccountServiceInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Framework\App\Action\Context;

/**
 * FrontendMagentoCustomerEnricher
 *
 * @inheritdoc
 *
 * The Gigya data shall be set previously on session objects 'gigya_account_data' and 'gigya_account_logging_email'
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
     * @param GigyaAccountServiceInterface $gigyaAccountService
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param Context $context
     */
    public function __construct(
        GigyaAccountServiceInterface $gigyaAccountService,
        GigyaSyncHelper $gigyaSyncHelper,
        Context $context
    ) {
        parent::__construct($gigyaAccountService, $gigyaSyncHelper);

        $this->context = $context;
    }

    /**
     * @inheritdoc
     *
     * Based on the request's action name, return true if we are going to login, create or update an account.
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
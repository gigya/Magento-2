<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Magento\Customer\Model\Session;
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
    /** @var  GigyaSyncHelper */
    protected $gigyaSyncHelper;

    /** @var  Session */
    protected $session;

    /** @var Context */
    protected $context;

    /**
     * FrontendMagentoCustomerEnricher constructor.
     *
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param Session $session
     * @param Context $context
     */
    public function __construct(
        GigyaSyncHelper $gigyaSyncHelper,
        Session $session,
        Context $context
    ) {
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->session = $session;
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

    /**
     * @inheritdoc
     *
     * The session variables 'gigya_account_data' and 'gigya_account_logging_email' must be set before.
     */
    protected function enrichMagentoCustomer($magentoCustomer)
    {
        $gigyaAccountData = $this->session->getGigyaAccountData();
        $gigyaAccountLoggingEmail = $this->session->getGigyaAccountLoggingEmail();

        $this->gigyaSyncHelper->updateMagentoCustomerWithGygiaAccount($magentoCustomer, $gigyaAccountData, $gigyaAccountLoggingEmail);
    }
}
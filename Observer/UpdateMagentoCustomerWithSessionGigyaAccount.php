<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Observer;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use \Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Action\Context;

/**
 * UpdateMagentoCustomerWithSessionGigyaAccount
 *
 * Observer that will enrich a Magento customer entity's required fields with the Gigya account data.
 *
 * Those data shall be set previously on session objects 'gigya_logged_in_account' and 'gigya_logged_in_email'
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class UpdateMagentoCustomerWithSessionGigyaAccount implements ObserverInterface
{
    /** @var  GigyaSyncHelper */
    protected $gigyaSyncHelper;

    /** @var  CustomerRepository */
    protected $customerRepository;

    /** @var  Session */
    protected $session;

    /** @var  Context */
    protected $context;

    public function __construct(
        GigyaSyncHelper $gigyaSyncHelper,
        CustomerRepository $customerRepository,
        Session $session,
        Context $context
    )
    {
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->customerRepository = $customerRepository;
        $this->session = $session;
        $this->context = $context;
    }

    /**
     * Based on the request's action name : check if Magento customer entity must be enriched with the current Gigya account data stored on session.
     *
     * So far it's the case when we are going to login, create or update an account from frontend.
     *
     * @return bool
     */
    protected function shallUpdateMagentoCustomerWithSessionGigyaAccount()
    {
        $actionName = $this->context->getRequest()->getActionName();

        return $actionName == 'loginPost'
            || $actionName == 'createPost'
            || $actionName == 'editPost';
    }

    /**
     * Will synchronize Magento account entity with the current Gigya account data stored on session.
     *
     * @param Observer $observer Must hang a data 'customer' of type Magento\Customer\Model\Customer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if ($this->shallUpdateMagentoCustomerWithSessionGigyaAccount()) {
            /** @var Customer $customer */
            $magentoCustomer = $observer->getData('customer');
            /** @var GigyaUser $gigyaLoggedInAccount */
            $gigyaLoggedInAccount = $this->session->getGigyaLoggedInAccount();
            /** @var string $gigyaLoggedInEmail */
            $gigyaLoggedInEmail = $this->session->getGigyaLoggedInEmail();

            $this->gigyaSyncHelper->updateMagentoCustomerWithGygiaAccount($magentoCustomer, $gigyaLoggedInAccount,
                $gigyaLoggedInEmail);
        }
    }
}
<?php
/**
 * Gigya Controller overriding Magento Customer module edit profile
 * This is mainly a placeholder for customizing profile edit,
 * currently no customization is made on original Account controller.
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Gigya\GigyaIM\Controller\Raas;

use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\InputException;
use Gigya\GigyaIM\Helper\GigyaSyncHelper as SyncHelper;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GigyaEditPost extends \Magento\Customer\Controller\AbstractAccount
{
    /** @var AccountManagementInterface */
    protected $customerAccountManagement;

    /** @var CustomerRepositoryInterface  */
    protected $customerRepository;

    /** @var Validator */
    protected $formKeyValidator;

    /** @var CustomerExtractor */
    protected $customerExtractor;

    /**
     * @var Session
     */
    protected $session;

    /** @var GigyaMageHelper  */
    protected $gigyaMageHelper;

    /** @var  SyncHelper */
    protected $syncHelper;

    /**
     * @param Context $context
     * @param Session $customerSession
     * @param AccountManagementInterface $customerAccountManagement
     * @param CustomerRepositoryInterface $customerRepository
     * @param Validator $formKeyValidator
     * @param CustomerExtractor $customerExtractor
     * @param SyncHelper $syncHelper
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        AccountManagementInterface $customerAccountManagement,
        CustomerRepositoryInterface $customerRepository,
        Validator $formKeyValidator,
        CustomerExtractor $customerExtractor,
        SyncHelper $syncHelper
    ) {
        $this->session = $customerSession;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->customerRepository = $customerRepository;
        $this->formKeyValidator = $formKeyValidator;
        $this->customerExtractor = $customerExtractor;
        parent::__construct($context);
        $this->gigyaMageHelper = $this->_objectManager->create('Gigya\GigyaIM\Helper\GigyaMageHelper');
        $this->syncHelper = $syncHelper;
    }

    /**
     * Change customer password action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $resultRedirect->setPath('*/*/edit');
        }

        if ($this->getRequest()->isPost()) {
            $customerId = $this->session->getCustomerId();

            try{

                $valid_gigya_user = $this->session->getGigyaRawData();
                $currentCustomer = $this->syncHelper->gigyaSync($valid_gigya_user);

            }catch(\Exception $e) {

                $this->messageManager->addError($e->getMessage());
            }

            //$currentCustomer = $this->customerRepository->getById($customerId);

            // Prepare new customer data
            $customer = $this->customerExtractor->extract('customer_account_edit', $this->_request);
            $customer->setId($customerId);
            if ($customer->getAddresses() == null) {
                $customer->setAddresses($currentCustomer->getAddresses());
            }

            // Change customer password
            if ($this->getRequest()->getParam('change_password')) {
                $this->changeCustomerPassword($currentCustomer->getEmail());
            }

//          dispatch field mapping event
            // CATODO : gigya_user is the Gigya data from frontend (the front page has made a request to Gigya to get these infos)
            // => we don't have the loginIDs data, which are mandatory for CMS sync process
            // CATODO : in GigyaPost we validate the user data with a call to Gigya. Here in GigyaEditPost we don't do that
            // => perhaps we should. And perhaps in the same time we could get the missing Gigya data (loginIDs)

            $gigya_user_arr = json_decode($this->getRequest()->getParam('gigya_user'), true);
            $user_obj = $this->gigyaMageHelper->userObjFromArr($gigya_user_arr);

            $this->_eventManager->dispatch('gigya_account_edited',[
                "customer" => $currentCustomer
            ]);

            $this->_eventManager->dispatch('gigya_post_user_create',[
                "gigya_user" => $user_obj,
                "customer" => $customer
            ]);

            try {
                $this->customerRepository->save($customer);
            } catch (AuthenticationException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (InputException $e) {
                $this->messageManager->addException($e, __('Invalid input'));
            } catch (\Exception $e) {
                $message = __('We can\'t save the customer.')
                    . $e->getMessage()
                    . '<pre>' . $e->getTraceAsString() . '</pre>';
                $this->messageManager->addException($e, $message);
            }

            if ($this->messageManager->getMessages()->getCount() > 0) {
                $this->session->setCustomerFormData($this->getRequest()->getPostValue());
                return $resultRedirect->setPath('*/*/edit');
            }

            $this->messageManager->addSuccess(__('You saved the account information.'));
            return $resultRedirect->setPath('customer/account');
        }

        return $resultRedirect->setPath('*/*/edit');
    }

    /**
     * Change customer password
     *
     * @param string $email
     * @return $this
     */
    protected function changeCustomerPassword($email)
    {
        $currPass = $this->getRequest()->getPost('current_password');
        $newPass = $this->getRequest()->getPost('password');
        $confPass = $this->getRequest()->getPost('password_confirmation');

        if (!strlen($newPass)) {
            $this->messageManager->addError(__('Please enter new password.'));
            return $this;
        }

        if ($newPass !== $confPass) {
            $this->messageManager->addError(__('Confirm your new password.'));
            return $this;
        }

        try {
            $this->customerAccountManagement->changePassword($email, $currPass, $newPass);
        } catch (AuthenticationException $e) {
            $this->messageManager->addError($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('Something went wrong while changing the password.'));
        }

        return $this;
    }
}

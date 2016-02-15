<?php
/**
 *
 */
namespace Gigya\GigyaM2\Controller\Raas;

use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Helper\Address;
use Magento\Framework\UrlFactory;
use Magento\Customer\Model\Metadata\FormFactory;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Customer\Model\Registration;
use Magento\Framework\Escaper;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Exception\InputException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GigyaPost extends \Magento\Customer\Controller\AbstractAccount
{
    /** @var AccountManagementInterface */
    protected $accountManagement;

    /** @var Address */
    protected $addressHelper;

    /** @var FormFactory */
    protected $formFactory;

    /** @var SubscriberFactory */
    protected $subscriberFactory;

    /** @var RegionInterfaceFactory */
    protected $regionDataFactory;

    /** @var AddressInterfaceFactory */
    protected $addressDataFactory;

    /** @var Registration */
    protected $registration;

    /** @var CustomerInterfaceFactory */
    protected $customerDataFactory;

    /** @var CustomerUrl */
    protected $customerUrl;

    /** @var Escaper */
    protected $escaper;

    /** @var CustomerExtractor */
    protected $customerExtractor;

    /** @var \Magento\Framework\UrlInterface */
    protected $urlModel;

    /** @var DataObjectHelper  */
    protected $dataObjectHelper;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var AccountRedirect
     */
    private $accountRedirect;

    protected $gigyaHelper;

    /**
     * @param Context $context
     * @param Session $customerSession
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param AccountManagementInterface $accountManagement
     * @param Address $addressHelper
     * @param UrlFactory $urlFactory
     * @param FormFactory $formFactory
     * @param SubscriberFactory $subscriberFactory
     * @param RegionInterfaceFactory $regionDataFactory
     * @param AddressInterfaceFactory $addressDataFactory
     * @param CustomerInterfaceFactory $customerDataFactory
     * @param CustomerUrl $customerUrl
     * @param Registration $registration
     * @param Escaper $escaper
     * @param CustomerExtractor $customerExtractor
     * @param DataObjectHelper $dataObjectHelper
     * @param AccountRedirect $accountRedirect
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        AccountManagementInterface $accountManagement,
        Address $addressHelper,
        UrlFactory $urlFactory,
        FormFactory $formFactory,
        SubscriberFactory $subscriberFactory,
        RegionInterfaceFactory $regionDataFactory,
        AddressInterfaceFactory $addressDataFactory,
        CustomerInterfaceFactory $customerDataFactory,
        CustomerUrl $customerUrl,
        Registration $registration,
        Escaper $escaper,
        CustomerExtractor $customerExtractor,
        DataObjectHelper $dataObjectHelper,
        AccountRedirect $accountRedirect
    ) {
        $this->session = $customerSession;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->accountManagement = $accountManagement;
        $this->addressHelper = $addressHelper;
        $this->formFactory = $formFactory;
        $this->subscriberFactory = $subscriberFactory;
        $this->regionDataFactory = $regionDataFactory;
        $this->addressDataFactory = $addressDataFactory;
        $this->customerDataFactory = $customerDataFactory;
        $this->customerUrl = $customerUrl;
        $this->registration = $registration;
        $this->escaper = $escaper;
        $this->customerExtractor = $customerExtractor;
        $this->urlModel = $urlFactory->create();
        $this->dataObjectHelper = $dataObjectHelper;
        $this->accountRedirect = $accountRedirect;
        parent::__construct($context);
        $this->gigyaHelper = $this->_objectManager->create('Gigya\GigyaM2\Helper\Data');
    }

    /**
     * Add address to customer during create account
     *
     * @return AddressInterface|null
     */
    protected function extractAddress()
    {
        if (!$this->getRequest()->getPost('create_address')) {
            return null;
        }

        $addressForm = $this->formFactory->create('customer_address', 'customer_register_address');
        $allowedAttributes = $addressForm->getAllowedAttributes();

        $addressData = [];

        $regionDataObject = $this->regionDataFactory->create();
        foreach ($allowedAttributes as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            $value = $this->getRequest()->getParam($attributeCode);
            if ($value === null) {
                continue;
            }
            switch ($attributeCode) {
                case 'region_id':
                    $regionDataObject->setRegionId($value);
                    break;
                case 'region':
                    $regionDataObject->setRegion($value);
                    break;
                default:
                    $addressData[$attributeCode] = $value;
            }
        }
        $addressDataObject = $this->addressDataFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $addressDataObject,
            $addressData,
            '\Magento\Customer\Api\Data\AddressInterface'
        );
        $addressDataObject->setRegion($regionDataObject);

        $addressDataObject->setIsDefaultBilling(
            $this->getRequest()->getParam('default_billing', false)
        )->setIsDefaultShipping(
            $this->getRequest()->getParam('default_shipping', false)
        );
        return $addressDataObject;
    }

    /**
     * Create customer account action
     * @return \Magento\Framework\Controller\Result\Forward|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($this->session->isLoggedIn() || !$this->registration->isAllowed()) {
            $resultRedirect->setPath('*/*/');
            return $resultRedirect;
        }

        if (!$this->getRequest()->isPost()) {
            $url = $this->urlModel->getUrl('*/*/create', ['_secure' => true]);
            $resultRedirect->setUrl($this->_redirect->error($url));
            return $resultRedirect;
        }

        $this->session->regenerateId();

        /////////////////////////
        // Gigya logic        //
        ////////////////////////
        // validate user
        $gigya_user_validated = $this->gigyaValidateUser();
        if (!$gigya_user_validated) {
            $this->messageManager->addError(__('The user is not validated. Please try again or contact your accounts manager provider.')); // add this message to documentation
            return $this->accountRedirect->getRedirect();
        } else {
            $gigya_user_account = $this->getGigyaAccount();
            // TODO: handle if email is missing.
            // TODO: try to do this without accountManagement. instantiate customerRepository in this class instead, and use it directly.
            $customer = $this->accountManagement->gigyaUserExists($gigya_user_account['loginIDs']['emails'][0]);
            if($customer) {
                $this->gigyaUpdateCustomer($customer, $gigya_user_account);
                $this->gigyaLoginUser($customer);
                return $this->accountRedirect->getRedirect();
            } else {
                $redirect = $this->gigyaCreateUser($resultRedirect,$gigya_user_account);
                return $redirect;
            }
        }
    }

    /**
     * Update Magento customer details with gigya user fields, upon each login
     * @param $customer
     * @param $gigya_user_account
     */
    protected function gigyaUpdateCustomer($customer, $gigya_user_account) {
        $customer->setFirstname($gigya_user_account["profile"]["firstName"]);
        $customer->setLastname($gigya_user_account["profile"]["lastName"]);
        $customer->setEmail($gigya_user_account['loginIDs']['emails'][0]);
        // add extra mapped fields here
    }

    /**
     * Make sure that password and password confirmation matched
     *
     * @param string $password
     * @param string $confirmation
     * @return void
     * @throws InputException
     */
    protected function checkPasswordConfirmation($password, $confirmation)
    {
        if ($password != $confirmation) {
            throw new InputException(__('Please make sure your passwords match.'));
        }
    }

    /**
     * Retrieve success message
     *
     * @return string
     */
    protected function getSuccessMessage()
    {
        if ($this->addressHelper->isVatValidationEnabled()) {
            if ($this->addressHelper->getTaxCalculationAddressType() == Address::TYPE_SHIPPING) {
                // @codingStandardsIgnoreStart
                $message = __(
                    'If you are a registered VAT customer, please <a href="%1">click here</a> to enter your shipping address for proper VAT calculation.',
                    $this->urlModel->getUrl('customer/address/edit')
                );
                // @codingStandardsIgnoreEnd
            } else {
                // @codingStandardsIgnoreStart
                $message = __(
                    'If you are a registered VAT customer, please <a href="%1">click here</a> to enter your billing address for proper VAT calculation.',
                    $this->urlModel->getUrl('customer/address/edit')
                );
                // @codingStandardsIgnoreEnd
            }
        } else {
            $message = __('Thank you for registering with %1.', $this->storeManager->getStore()->getFrontendName());
        }
        return $message;
    }

    /**
     * Use Gigya Data Helper to validate user
     * @return bool
     */
    protected function gigyaValidateUser() {
        $b_valid_gigya_user = false;
        $gigya_validation_post = $this->getRequest()->getParam('login_data');
        $gigya_validation_object = json_decode($gigya_validation_post);
        $b_valid_gigya_user = $this->gigyaHelper->_validateRaasUser($gigya_validation_object); // (adding helper as dependency injection in constructor doesn't work in Controller)
        return $b_valid_gigya_user;
    }

    /**
     * get account info for validated user
     * @return mixed
     */
    protected function getGigyaAccount() {
        $gigya_uid = json_decode($this->getRequest()->getParam('login_data'))->UID;
        $gigya_account = $this->gigyaHelper->_getAccount($gigya_uid);
        return $gigya_account;
    }

    /**
     * @param $customer
     */
    protected function gigyaLoginUser($customer) {
        try {
            $this->session->setCustomerDataAsLoggedIn($customer);
            $this->session->regenerateId();
        } catch (EmailNotConfirmedException $e) {
            $value = $this->customerUrl->getEmailConfirmationUrl($customer['data']['email']);
            $message = __(
                'This account is not confirmed. <a href="%1">Click here</a> to resend confirmation email.',
                $value
            );
            $this->messageManager->addError($message);
            $this->session->setUsername($customer['data']['email']);
        } catch (AuthenticationException $e) {
            $message = __('Invalid login or password.');
            $this->messageManager->addError($message);
            $this->session->setUsername($customer['data']['email']);
        } catch (\Exception $e) {
            // PA DSS violation: throwing or logging an exception here can disclose customer password
            $this->messageManager->addError(
                __('An unspecified error occurred. Please contact us for assistance.')
            );
        }
    }

    /**
     * Create new user with Gigya user details
     * @param $resultRedirect
     * @param $gigya_user_account
     * @return \Magento\Framework\Controller\Result\Forward|\Magento\Framework\Controller\Result\Redirect
     */
    protected function gigyaCreateUser($resultRedirect, $gigya_user_account) {
        try {
        //    $address = $this->extractAddress();
        //    $addresses = $address === null ? [] : [$address];

            $customer = $this->customerExtractor->extract('customer_account_create', $this->_request);
        //    $customer->setAddresses($addresses);
            $customer->setEmail($gigya_user_account['loginIDs']['emails'][0]);
            $customer->setFirstname($gigya_user_account['profile']['firstName']);
            $customer->setLastname($gigya_user_account['profile']['lastName']);
            // Map additional fields here

        ///   $password = $this->getRequest()->getParam('password');
         //   $confirmation = $this->getRequest()->getParam('password_confirmation');
            $password =  $this->_objectManager->create('Gigya\GigyaM2\Helper\Data')->generatePassword();

            $redirectUrl = $this->session->getBeforeAuthUrl();

        //    $this->checkPasswordConfirmation($password, $confirmation);

            $customer = $this->accountManagement
                ->createAccount($customer, $password, $redirectUrl);

            if ($this->getRequest()->getParam('is_subscribed', false)) {
                $this->subscriberFactory->create()->subscribeCustomerById($customer->getId());
            }

            $this->_eventManager->dispatch(
                'customer_register_success',
                ['account_controller' => $this, 'customer' => $customer]
            );

            $confirmationStatus = $this->accountManagement->getConfirmationStatus($customer->getId());
            if ($confirmationStatus === AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
                $email = $this->customerUrl->getEmailConfirmationUrl($customer->getEmail());
                // @codingStandardsIgnoreStart
                $this->messageManager->addSuccess(
                    __(
                        'You must confirm your account. Please check your email for the confirmation link or <a href="%1">click here</a> for a new link.',
                        $email
                    )
                );
                // @codingStandardsIgnoreEnd
                $url = $this->urlModel->getUrl('*/*/index', ['_secure' => true]);
                $resultRedirect->setUrl($this->_redirect->success($url));
            } else {
                $this->session->setCustomerDataAsLoggedIn($customer);
                $this->messageManager->addSuccess($this->getSuccessMessage());
                $resultRedirect = $this->accountRedirect->getRedirect();
            }
            return $resultRedirect;
        } catch (StateException $e) {
            $url = $this->urlModel->getUrl('customer/account/forgotpassword');
            // @codingStandardsIgnoreStart
            $message = __(
                'There is already an account with this email address. If you are sure that it is your email address, <a href="%1">click here</a> to get your password and access your account.',
                $url
            );
            // @codingStandardsIgnoreEnd
            $this->messageManager->addError($message);
        } catch (InputException $e) {
            $this->messageManager->addError($this->escaper->escapeHtml($e->getMessage()));
            foreach ($e->getErrors() as $error) {
                $this->messageManager->addError($this->escaper->escapeHtml($error->getMessage()));
            }
        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('We can\'t save the customer.'));
        }

        $this->session->setCustomerFormData($this->getRequest()->getPostValue());
        $defaultUrl = $this->urlModel->getUrl('*/*/create', ['_secure' => true]);
        $resultRedirect->setUrl($this->_redirect->error($defaultUrl));
        return $resultRedirect;
    }

}

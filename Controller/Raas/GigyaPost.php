<?php
/**
 * Gigya Controller overriding Magento Customer module Login & Registration controllers. (as defined in etc/di.xml)
 * Add Gigya user validation and account info before continue with Customer flows.
 */
namespace Gigya\GigyaIM\Controller\Raas;

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
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Event\Manager;

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

    /**
     * @var EventManager
     */
    private $eventManager;

    protected $gigyaMageHelper;

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
        AccountRedirect $accountRedirect,
        EventManager $eventManager
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
        $this->eventManager = $eventManager;
        parent::__construct($context);
        $this->gigyaMageHelper = $this->_objectManager->create('Gigya\GigyaIM\Helper\GigyaMageHelper');
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

        // Gigya logic: validate gigya user -> get Gigya account info -> check if account exists in Magento ->
        // login /create in magento :

        $valid_gigya_user = $this->gigyaValidateUser();
        // if gigya user not validated return error
        if (!$valid_gigya_user) {
            $this->messageManager->addError(__('The user is not validated. Please try again or contact support.'));
            return $this->accountRedirect->getRedirect();
        }
        // we have a valid gigya user. verify that required fields exist
        else {
            $required_field_message = $this->gigyaMageHelper->verifyGigyaRequiredFields($valid_gigya_user);
            if (!empty($required_field_message)) {
                foreach ($required_field_message as $message) {
                    $this->messageManager->addError($message);
                }
                return $this->accountRedirect->getRedirect();
            }

            // Required fields exist, check if user exists in Magento
            // (consider doing this without overriding accountManagement.
                // instantiate customerRepository in this class instead, and use it directly)
            $customer = $this->accountManagement->gigyaUserExists($valid_gigya_user->getGigyaLoginId());
            if ($customer) {
                $this->gigyaSetCustomerFields($customer, $valid_gigya_user);
                $this->accountManagement->gigyaUpdateCustomer($customer);
                $this->gigyaLoginUser($customer);
                $redirect = $this->accountRedirect->getRedirect();
            } else {
                $redirect = $this->gigyaCreateUser($resultRedirect, $valid_gigya_user);
            }
            // dispatch field mapping event
            $this->eventManager->dispatch('gigya_post_user_create',[
                "gigya_user" => $valid_gigya_user,
                "customer" => $customer
            ]);
            return $redirect;
        }
    }

    /**
     * Use gigyaMageHelper to validate and get user
     * @return false/object:gigya_user
     */
    protected function gigyaValidateUser()
    {
        $gigya_validation_post = $this->getRequest()->getParam('login_data');
        $gigya_validation_o = json_decode($gigya_validation_post);
        $valid_gigya_user = $this->gigyaMageHelper->validateRaasUser(
            $gigya_validation_o->UID,
            $gigya_validation_o->UIDSignature,
            $gigya_validation_o->signatureTimestamp
        );
        return $valid_gigya_user;
    }

    /**
     * @param object $customer
     * @param object $gigya_user_account
     */
    protected function gigyaSetCustomerFields(&$customer, $gigya_user_account)
    {
        $customer->setEmail($gigya_user_account->getGigyaLoginId());
        $customer->setFirstname($gigya_user_account->getProfile()->getFirstName());
        $customer->setLastname($gigya_user_account->getProfile()->getLastName());
        $customer->setCustomAttribute("gigya_uid", $gigya_user_account->getUID());

        ///////////////////////////////////////////////////////
        // adding extra mapped fields :
        //  create the custom attribute in Gigya.
        //  See Magento prepared methods at:
        //  app/code/Magento/Customer/Model/Data/Customer.php
        //
        // Examples:
        // $customer->setPrefix($gigya_user_account["data"]["prefix"]); // after setting data->prefix in Gigya
        // $customer->setMiddlename($gigya_user_account["data"]["middlename"]); // after setting data->middlename in Gigya
        // $customer->setAddresses($addresses);

        // When needed, Don't forget to map Gigya to Magento field values.
        // Example
        $this->gigyaSetGender($customer, $gigya_user_account);
        //
        // Mapping Magento custom fields:
        // helpful magento guide for creating custom fields:
        // https://maxyek.wordpress.com/2015/10/22/building-magento-2-extension-customergrid/comment-page-1/
        //
        // For mapping existing Magento custom fields to gigya fields:
        // use: $customer->setCustomAttribute($attributeCode, $attributeValue);
        // or: $customer->setCustomAttributes(array());
        // located at: /lib/internal/Magento/Framework/Api/AbstractExtensibleObject
        //////////////////////////////////////////////////////// $gigya_user_account["profile"]["GUID"]
    //    $custom_attributes = $customer->getCustomAttributes();
    }

    /**
     * Example method to handle field mapping for custom fields
     * @param $customer
     * @param $gigya_user_account
     */
    protected function gigyaSetGender(&$customer, $gigya_user_account)
    {
        $gender = $gigya_user_account->getProfile()->getGender();
        if ($gender) {
            if ($gender = "m") {
                $customer->setGender("1");
            } elseif ($gender = "f") {
                $customer->setGender("2");
            }
        }
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
     * @param $customer
     */
    protected function gigyaLoginUser($customer)
    {
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
    protected function gigyaCreateUser($resultRedirect, $gigya_user_account)
    {
        try {
            $customer = $this->customerExtractor->extract('customer_account_create', $this->_request);

             $this->gigyaSetCustomerFields($customer, $gigya_user_account);

            $password =  $this->gigyaMageHelper->generatePassword();
            $redirectUrl = $this->session->getBeforeAuthUrl();

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

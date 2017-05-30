<?php

namespace Gigya\GigyaIM\Controller\Raas;

use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\DataObject;
use Magento\Framework\Stdlib\CookieManagerInterface;
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
use Magento\Customer\Api\CustomerRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper as SyncHelper;

abstract class AbstractLogin extends \Magento\Customer\Controller\AbstractAccount
{
    const RESPONSE_OBJECT = 'response_object';
    const RESPONSE_DATA = 'response_data';

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

    /** @var DataObjectHelper */
    protected $dataObjectHelper;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var AccountRedirect
     */
    protected $accountRedirect;

    /** @var  \Gigya\GigyaIM\Helper\GigyaMageHelper */
    protected $gigyaMageHelper;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /** @var  SyncHelper */
    protected $syncHelper;

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
     * @param CustomerRepositoryInterface $customerRepository
     * @param SyncHelper $syncHelper
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
        CustomerRepositoryInterface $customerRepository,
        SyncHelper $syncHelper,
        Validator $formKeyValidator,
        CookieManagerInterface $cookieManager
    )
    {
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
        $this->gigyaMageHelper = $this->_objectManager->create('Gigya\GigyaIM\Helper\GigyaMageHelper');
        $this->customerRepository = $customerRepository;
        $this->syncHelper = $syncHelper;
        $this->formKeyValidator = $formKeyValidator;
        $this->cookieManager = $cookieManager;
    }

    /**
     * @param \Gigya\CmsStarterKit\user\GigyaUser $valid_gigya_user
     * @return DataObject
     */
    protected function doLogin(\Gigya\CmsStarterKit\user\GigyaUser $valid_gigya_user)
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        // if gigya user not validated return error
        if (!$valid_gigya_user) {
            $this->messageManager->addError(__('The user is not validated. Please try again or contact support.'));
            return $redirect = $this->encapsulateResponse($this->accountRedirect->getRedirect());
        } // we have a valid gigya user. verify that required fields exist
        else {
            $required_field_message = $this->gigyaMageHelper->verifyGigyaRequiredFields($valid_gigya_user);

            if (!empty($required_field_message)) {
                foreach ($required_field_message as $message) {
                    $this->messageManager->addError($message);
                }
                return $this->encapsulateResponse($this->accountRedirect->getRedirect());
            }

            $redirect = $this->encapsulateResponse($this->accountRedirect->getRedirect());

            try {

                $customer = $this->gigyaMageHelper->setMagentoLoggingContext($valid_gigya_user);

                if ($customer) {
                    $this->gigyaLoginUser($customer);
                    $this->customerRepository->save($customer);
                } else {
                    $redirect = $this->gigyaCreateUser($resultRedirect, $valid_gigya_user);
                }
            } catch(\Exception $e) {
                $this->messageManager->addError($e->getMessage());
                $defaultUrl = $this->urlModel->getUrl('customer/login', ['_secure' => true]);
                $redirect = $this->createResponseDataObject($this->_redirect->error($defaultUrl));
            }

            return $redirect;
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
            $this->messageManager->addErrorMessage($message);
            $this->session->setUsername($customer['data']['email']);
        } catch (AuthenticationException $e) {
            $message = __('Invalid login or password.');
            $this->messageManager->addErrorMessage($message);
            $this->session->setUsername($customer['data']['email']);
        } catch (\Exception $e) {
            // PA DSS violation: throwing or logging an exception here can disclose customer password
            $this->messageManager->addErrorMessage(
                __('An unspecified error occurred. Please contact us for assistance.')
            );
        }
    }

    /**
     * Create new user with Gigya user details
     * @param $resultRedirect
     * @param $gigya_user_account
     *
     * @return DataObject
     */
    protected function gigyaCreateUser($resultRedirect, $gigya_user_account)
    {
        try {
            $customer = $this->customerExtractor->extract('customer_account_create', $this->_request);

            $password = $this->gigyaMageHelper->generatePassword();
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

                return $this->encapsulateResponse($this->accountRedirect->getRedirect());
            }
        } catch (StateException $e) {
            $url = $this->urlModel->getUrl('customer/account/forgotpassword');
            // @codingStandardsIgnoreStart
            $message = __(
                'There is already an account with this email address. If you are sure that it is your email address, <a href="%1">click here</a> to get your password and access your account.',
                $url
            );
            // @codingStandardsIgnoreEnd
            $this->messageManager->addErrorMessage($message);
        } catch (InputException $e) {
            $this->messageManager->addErrorMessage($this->escaper->escapeHtml($e->getMessage()));
            foreach ($e->getErrors() as $error) {
                $this->messageManager->addErrorMessage($this->escaper->escapeHtml($error->getMessage()));
            }
        } catch (\Exception $e) {
            $message = __('We can\'t save the customer. ') . $e->getMessage();
            $this->messageManager->addErrorMessage($message);
        }

        $this->session->setCustomerFormData($this->getRequest()->getPostValue());
        $defaultUrl = $this->urlModel->getUrl('*/*/create', ['_secure' => true]);
        $resultRedirect->setUrl($this->_redirect->error($defaultUrl));
        return $this->createResponseDataObject($resultRedirect);
    }

    /**
     * @param string $type
     * @param string $url
     * @return DataObject
     */
    protected function createResponseDataObject($url, $additionalData = [])
    {
        return new DataObject([
            self::RESPONSE_OBJECT => $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setUrl($url),
            self::RESPONSE_DATA => $additionalData
        ]);
    }

    /**
     * @param \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\Result\Forward $resultRedirect
     * @return DataObject
     */
    protected function encapsulateResponse($resultRedirect)
    {
        return new DataObject([
            self::RESPONSE_OBJECT => $resultRedirect,
            self::RESPONSE_DATA => []
        ]);
    }

    /**
     * @param DataObject $object
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\Result\Forward $resultRedirect
     */
    protected function extractResponseFromDataObject(DataObject $object)
    {
        return $object->getData(self::RESPONSE_OBJECT);
    }

    /**
     * @param DataObject $object
     * @return array
     */
    protected function extractDataFromDataObject(DataObject $object)
    {
        return $object->getData(self::RESPONSE_DATA);
    }
}

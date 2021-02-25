<?php

namespace Gigya\GigyaIM\Controller\Raas;

use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Logger\Logger;
use Gigya\GigyaIM\Model\Session\Extend;

use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
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
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\Forward;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Framework\Controller\Result\JsonFactory;

abstract class AbstractLogin extends \Magento\Customer\Controller\AbstractAccount
{
    const RESPONSE_OBJECT = 'response_object';
    const RESPONSE_DATA = 'response_data';

    const RETRY_COOKIE_NAME = 'gig_login_retry';

    const EVENT_POST_USER_LOGIN = 'gigya_post_user_login';

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

    /** @var  GigyaMageHelper */
    protected $gigyaMageHelper;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /** @var  GigyaSyncHelper */
    protected $gigyaSyncHelper;

    /** @var array */
    protected $cookies;

    /** @var array */
    protected $cookiesToDelete;

    /**
     * @var Extend
     */
    protected $extendModel;

    /**
     * @var array
     */
    protected $messageStorage;

    protected $scopeConfig;
    protected $formKeyValidator;
    protected $cookieManager;
    protected $cookieMetadataFactory;
    protected $storeManager;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

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
     * @param GigyaSyncHelper $gigyaSyncHelper
     * @param Validator $formKeyValidator
     * @param CookieManagerInterface $cookieManager
     * @param GigyaMageHelper $gigyaMageHelper
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param Extend $extendModel
	 * @param JsonFactory $resultJsonFactory
     * @param Logger $logger
     * @param JsonSerializer $jsonSerializer
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
		GigyaSyncHelper $gigyaSyncHelper,
		Validator $formKeyValidator,
		CookieManagerInterface $cookieManager,
		GigyaMageHelper $gigyaMageHelper,
		CookieMetadataFactory $cookieMetadataFactory,
		Extend $extendModel,
        JsonFactory $resultJsonFactory,
        Logger $logger,
        JsonSerializer $jsonSerializer
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
		$this->gigyaMageHelper = $gigyaMageHelper;
		$this->customerRepository = $customerRepository;
		$this->gigyaSyncHelper = $gigyaSyncHelper;
		$this->formKeyValidator = $formKeyValidator;
		$this->cookieManager = $cookieManager;
		$this->cookieMetadataFactory = $cookieMetadataFactory;
		$this->storeManager = $storeManager;
		$this->extendModel = $extendModel;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;

		$this->cookies = [];
		$this->cookiesToDelete = [];
		$this->messageStorage = [];
	}

    /**
     * @param \Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser $valid_gigya_user
     * @return DataObject
     */
    protected function doLogin(\Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser $valid_gigya_user)
    {
        $this->logger->debug('Logging in with valid Gigya user: ' . $this->jsonSerializer->serialize($valid_gigya_user));

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        // if gigya user not validated return error
        if (!$valid_gigya_user) {
            $message = __('The user is not validated. Please try again or contact support.');
            $this->logger->debug('Login failed: ' . $message);
            $this->addError($message);
            return $redirect = $this->encapsulateResponse($this->accountRedirect->getRedirect(),
                ['login_successful' => false]);
        } // we have a valid gigya user. verify that required fields exist
        else {
            $required_field_message = $this->gigyaMageHelper->verifyGigyaRequiredFields($valid_gigya_user);

            if (!empty($required_field_message)) {
                $this->logger->debug(
                    'Login failed, required fields not provided: ' .
                    $this->jsonSerializer->serialize($required_field_message)
                );

                foreach ($required_field_message as $message) {
                    $this->addError($message);
                }

                return $this->encapsulateResponse($this->accountRedirect->getRedirect(), ['login_successful' => false]);
            }

            try {
                $customer = $this->gigyaSyncHelper->setMagentoLoggingContext($valid_gigya_user);

                if ($customer) {
                    $loginSuccess = $this->gigyaLoginUser($customer);
                    $this->customerRepository->save($customer);
                    $redirect = $this->encapsulateResponse(
                        $this->accountRedirect->getRedirect(), ['login_successful' => $loginSuccess]
                    );
                } else {
                    $redirect = $this->gigyaCreateUser($resultRedirect, $valid_gigya_user);
                }

                // dispatch gigya login event
                $this->_eventManager->dispatch(self::EVENT_POST_USER_LOGIN, [
                    "gigya_user" => $valid_gigya_user,
                    "customer" => $customer,
                    "accountManagement" => $this->accountManagement
                ]);
            } catch(\Exception $e) {
                $this->addError($e->getMessage());
                $this->logger->debug('Failed to login customer: ' . $e->getMessage());
                $redirect = $this->encapsulateResponse($this->accountRedirect->getRedirect());
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
     *
     * @return bool
     */
    protected function gigyaLoginUser($customer)
    {
        $this->logger->debug('Trying to log in with customer: ' . $this->jsonSerializer->serialize($customer));

        try {
            $this->session->setCustomerDataAsLoggedIn($customer);
            $this->session->regenerateId();
            $this->deleteLoginRetryCounter();
            $this->logger->debug('Customer successfully logged in');
            return true;
        } catch (EmailNotConfirmedException $e) {
            $value = $this->customerUrl->getEmailConfirmationUrl($customer['data']['email']);
            $message = __(
                'This account is not confirmed. <a href="%1">Click here</a> to resend confirmation email.',
                $value
            );
            $this->addError($message);
            $this->incrementLoginRetryCounter();
            $this->session->setUsername($customer['data']['email']);
            $this->logger->debug('Unable to login user: ' . $message);
            return false;
        } catch (AuthenticationException $e) {
            $message = __('Invalid login or password.');
            $this->addError($message);
            $this->incrementLoginRetryCounter();
            $this->session->setUsername($customer['data']['email']);
            $this->logger->debug('Unable to login user: ' . $message);
            return false;
        } catch (\Exception $e) {
            $this->incrementLoginRetryCounter();
            $message = __('An unspecified error occurred. Please contact us for assistance.');
            // PA DSS violation: throwing or logging an exception here can disclose customer password
            $this->addError($message);
            $this->logger->debug('Unable to login user: ' . $message);
            return false;
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
            $this->logger->debug(
                'Creating new Magento user from Gigya user: ' .
                $this->jsonSerializer->serialize($gigya_user_account)
            );
            $customer = $this->customerExtractor->extract('customer_account_create', $this->_request);
            $password = $this->gigyaMageHelper->generatePassword();
            $redirectUrl = $this->session->getBeforeAuthUrl();
            $customer = $this->accountManagement->createAccount($customer, $password, $redirectUrl);

            if ($this->getRequest()->getParam('is_subscribed', false)) {
                $this->subscriberFactory->create()->subscribeCustomerById($customer->getId());
            }

            $this->logger->debug('New Magento customer successfully created');

            $this->_eventManager->dispatch(
                'customer_register_success',
                ['account_controller' => $this, 'customer' => $customer]
            );

            $this->gigyaMageHelper->setSessionExpirationCookie();
            $confirmationStatus = $this->accountManagement->getConfirmationStatus($customer->getId());

            if ($confirmationStatus === AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
                $email = $this->customerUrl->getEmailConfirmationUrl($customer->getEmail());
                // @codingStandardsIgnoreStart
                $this->addSuccess(
                    __(
                        'You must confirm your account. Please check your email for the confirmation link or <a href="%1">click here</a> for a new link.',
                        $email
                    )
                );
                // @codingStandardsIgnoreEnd
                $url = $this->urlModel->getUrl('*/*/index', ['_secure' => true]);
                $this->incrementLoginRetryCounter();
                $resultRedirect->setUrl($this->_redirect->success($url));
            } else {
                $this->session->setCustomerDataAsLoggedIn($customer);
                $this->addSuccess($this->getSuccessMessage());
                $this->deleteLoginRetryCounter();
                return $this->encapsulateResponse($this->accountRedirect->getRedirect());
            }
        } catch (StateException $e) {
            $this->incrementLoginRetryCounter();
            $url = $this->urlModel->getUrl('customer/account/forgotpassword');
            // @codingStandardsIgnoreStart
            $message = __(
                'There is already an account with this email address. If you are sure that it is your email address, <a href="%1">click here</a> to get your password and access your account.',
                $url
            );
            $this->logger->debug('Failed to create Magento customer: ' . $message);
            // @codingStandardsIgnoreEnd
            $this->addError($message);
        } catch (InputException $e) {
            $this->incrementLoginRetryCounter();
            $this->addError($this->escaper->escapeHtml($e->getMessage()));
            foreach ($e->getErrors() as $error) {
                $this->addError($this->escaper->escapeHtml($error->getMessage()));
            }
            $this->logger->debug(
                'Failed to create Magento customer: ' . $this->jsonSerializer->serialize($e->getErrors())
            );
        } catch (\Exception $e) {
            $this->incrementLoginRetryCounter();;
            $message = __('We can\'t save the customer. ') . $e->getMessage();
            $this->logger->debug('Failed to create Magento customer: ' . $message);
            $this->addError($message);
        }

        $this->session->setCustomerFormData($this->getRequest()->getPostValue());
        $defaultUrl = $this->urlModel->getUrl('*/*/create', ['_secure' => true]);
        $resultRedirect->setUrl($this->_redirect->error($defaultUrl));
        return $this->createResponseDataObject($resultRedirect);
    }

    /**
     * @param string $url
     * @param array $additionalData
     *
     * @return DataObject
     */
    protected function createResponseDataObject($url, $additionalData = [])
    {
        $additionalData['location'] = $url;
        return new DataObject([
            self::RESPONSE_OBJECT => (is_string($url) ?
                $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setUrl($url) :
				(is_object($url) ? $url : null)),
            self::RESPONSE_DATA => $additionalData
        ]);
    }

    /**
     * @param \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\Result\Forward $resultRedirect
     * @param array $additionalData
     *
     * @return DataObject
     */
    protected function encapsulateResponse($resultRedirect, $additionalData = [])
    {
        $url = null;
        if($resultRedirect instanceof Redirect)
        {
            $response = serialize($this->getResponse());
            $response = unserialize($response);

            $resultRedirect->renderResult($response);
            $header = $response->getHeader('Location');
            $response->clearHeader('Location');
            /* @var $header \Zend\Http\Header\Location */
            if($header)
            {
                $url = $header->getUri();
            }
        }
        else
        if($resultRedirect instanceof Forward)
        {
            $request = $this->getRequest();
            $url = $this->urlModel->getUrl(
                sprintf('%s/%s/%s', $request->getModuleName(), $request->getControllerName(), $request->getActionName()),
                $request->getParams());

        }
        $additionalData['location'] = $url;
        return new DataObject([
            self::RESPONSE_OBJECT => $resultRedirect,
            self::RESPONSE_DATA => $additionalData
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

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    protected function setCookie($name, $value)
    {
        $this->cookies[$name] = $value;
        return $this;
    }

    /**
     * @param $name
     * @param $defaultValue
     * @return mixed
     */
    protected function getCookie($name, $defaultValue)
    {
        $defaultValue = (int) $this->cookieManager->getCookie($name, $defaultValue);
        if(!isset($this->cookies[$name]))
        {
            $this->cookies[$name] = $defaultValue;
        }
        return $this->cookies[$name];
    }

    /**
     * @return array
     */
    protected function getCookies()
    {
        return $this->cookies;
    }

    /**
     * @return bool
     */
    protected function isLoginRetryCounterExceeded()
    {
        return $this->getCookie(self::RETRY_COOKIE_NAME, 0) >= 3;
    }

    /**
     * @return $this
     */
    protected function incrementLoginRetryCounter()
    {
        return $this->setCookie(self::RETRY_COOKIE_NAME, (int) $this->getCookie(self::RETRY_COOKIE_NAME, 0)+1);
    }

    /**
     * @return $this
     */
    protected function deleteLoginRetryCounter()
    {
        $this->cookiesToDelete[self::RETRY_COOKIE_NAME] = true;
        if(isset($this->cookies[self::RETRY_COOKIE_NAME]))
        {
            unset($this->cookies[self::RETRY_COOKIE_NAME]);
        }
        return $this;
    }

	/**
	 * @return $this
	 *
	 * @throws CookieSizeLimitReachedException
	 * @throws FailureToSendException
	 * @throws InputException
	 */
	protected function applyCookies()
	{
		$metadata = $this->cookieMetadataFactory->createPublicCookieMetadata();

		$metadata->setDuration(60)->setPath($this->storeManager->getStore()->getStorePath());
		foreach ($this->cookies as $name => $value) {
			if (isset($this->cookiesToDelete[$name])) {
				$this->cookieManager->deleteCookie($name);
			} else {
				$this->cookieManager->setPublicCookie($name, $value, $metadata);
			}
		}

		return $this;
	}

    /**
     * @param string $message
     * @return $this
     */
    protected function addError($message)
    {
        return $this->addMessage($message, \Magento\Framework\Message\MessageInterface::TYPE_ERROR);
    }

    /**
     * @param string $message
     * @return $this
     */
    protected function addSuccess($message)
    {
        return $this->addMessage($message, \Magento\Framework\Message\MessageInterface::TYPE_SUCCESS);
    }

    /**
     * @param string $message
     * @param string $type
     * @return $this
     */
    protected function addMessage($message, $type)
    {
        if(!isset($this->messageStorage[$type]))
        {
            $this->messageStorage[$type] = [];
        }
        $this->messageStorage[$type][] = $message;
        return $this;
    }

    protected function applyMessages()
    {
        foreach($this->messageStorage as $type => $messages)
        {
            foreach($messages as $message)
            {
                switch($type) {
                    case \Magento\Framework\Message\MessageInterface::TYPE_SUCCESS:
                        $this->messageManager->addSuccessMessage($message);
                        break;

                    case \Magento\Framework\Message\MessageInterface::TYPE_ERROR:
                        $this->messageManager->addErrorMessage($message);
                        break;

                    default:
                        $this->messageManager->addNoticeMessage($message);
                        break;
                }
            }
        }
        return $this;
    }
}

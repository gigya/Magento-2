<?php

namespace Gigya\GigyaIM\Controller\Raas;

// Parent class constructor uses
use Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException;
use Magento\Customer\Controller\Account\LoginPost;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\Data\Form\FormKey\Validator;

// Magento`s CreatePost uses
use Magento\Customer\Helper\Address;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Framework\Escaper;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Framework\UrlInterface;

// Custom class uses
use Magento\Framework\UrlFactory;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Store\Model\StoreManagerInterface;
use Gigya\GigyaIM\Model\Session\Extend;
use Magento\Framework\Controller\Result\JsonFactory;
use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadataFactory;

/* CMS Starter Kit */
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;

class GigyaPost extends LoginPost
{
    /**
     * @var AccountManagementInterface
     */
    protected $customerAccountManagement;

    /**
     * @var Address
     */
    protected $addressHelper;

    /**
     * @var SubscriberFactory
     */
    protected $subscriberFactory;

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @var CustomerExtractor
     */
    protected $customerExtractor;

    /**
     * @var UrlInterface
     */
    protected $urlModel;

    /**
     * @var GigyaMageHelper
     */
    protected $gigyaMageHelper;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var GigyaSyncHelper
     */
    protected $gigyaSyncHelper;

    /**
     * @var CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    protected $cookieMetadataFactory;

    /**
     * @var Extend
     */
    protected $extendModel;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var GigyaConfig
     */
    protected $config;

    /**
     * @var array
     */
    protected $cookies;

    /**
     * @var array
     */
    protected $cookiesToDelete;

    /**
     * @var array
     */
    protected $messageStorage;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

	/**
	 * @var GigyaLogger
	 */
	protected $logger;

    /**
     * @var JsonSerializer
     */
	protected $jsonSerializer;

    /**
     * @var PublicCookieMetadataFactory
     */
	protected $publicCookieMetadataFactory;

	/**
	 * GigyaPost constructor.
	 *
	 * - Parent class parameters
	 * @param Context $context
	 * @param Session $customerSession
	 * @param AccountManagementInterface $customerAccountManagement
	 * @param CustomerUrl $customerUrl
	 * @param Validator $formKeyValidator
	 * @param AccountRedirect $accountRedirect
	 *
	 * - Magento CreatePost parameters
	 * These ones needs to be on this constructor because Gigya plugin join both actions: login and create
	 * @param $addressHelper Address
	 * @param SubscriberFactory $subscriberFactory
	 * @param Escaper $escaper
	 * @param CustomerExtractor $customerExtractor
	 * @param UrlInterface $urlModel
	 *
	 * - Custom parameters
	 * @param UrlFactory $urlFactory
	 * @param GigyaMageHelper $gigyaMageHelper
	 * @param CustomerRepositoryInterface $customerRepository
	 * @param GigyaSyncHelper $gigyaSyncHelper
	 * @param CookieManagerInterface $cookieManager
	 * @param CookieMetadataFactory $cookieMetadataFactory
	 * @param StoreManagerInterface $storeManager
	 * @param Extend $extendModel
	 * @param JsonFactory $resultJsonFactory
	 * @param GigyaConfig $config
	 * @param GigyaLogger $logger
     * @param JsonSerializer $jsonSerializer
     * @param PublicCookieMetadataFactory $publicCookieMetadataFactory
	 */
    public function __construct(
        // Parent class parameters
        Context $context,
        Session $customerSession,
        AccountManagementInterface $customerAccountManagement,
        CustomerUrl $customerUrl,
        Validator $formKeyValidator,
        AccountRedirect $accountRedirect,

        // Magento CreatePost parameters
        Address $addressHelper,
        SubscriberFactory $subscriberFactory,
        Escaper $escaper,
        CustomerExtractor $customerExtractor,
        UrlInterface $urlModel,

        // Custom parameters
        UrlFactory $urlFactory,
        GigyaMageHelper $gigyaMageHelper,
        CustomerRepositoryInterface $customerRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        StoreManagerInterface $storeManager,
        Extend $extendModel,
        JsonFactory $resultJsonFactory,
        GigyaConfig $config,
		GigyaLogger $logger,
        JsonSerializer $jsonSerializer,
        PublicCookieMetadataFactory $publicCookieMetadataFactory
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $customerAccountManagement,
            $customerUrl,
            $formKeyValidator,
            $accountRedirect
        );

        $this->gigyaMageHelper = $gigyaMageHelper;
        $this->customerRepository = $customerRepository;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->extendModel = $extendModel;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->logger = $logger;

        $this->cookies = [];
        $this->cookiesToDelete = [];
        $this->messageStorage = [];

        $this->urlModel = $urlFactory->create();
        $this->storeManager = $storeManager;

        $this->addressHelper = $addressHelper;
        $this->subscriberFactory = $subscriberFactory;
        $this->escaper = $escaper;
        $this->customerExtractor = $customerExtractor;
        $this->urlModel = $urlModel;
        $this->jsonSerializer = $jsonSerializer;
        $this->publicCookieMetadataFactory = $publicCookieMetadataFactory;
    }

	/**
	 * Gigya logic:
	 * 1. Validate gigya user
	 * 2. Get Gigya account info
	 * 3. Check if account exists in Magento
	 * 4. Login /create in magento
	 *
	 * @return Forward|\Magento\Framework\Controller\Result\Json|Redirect
	 *
	 * @throws CookieSizeLimitReachedException
	 * @throws FailureToSendException
	 * @throws InputException
	 * @throws \Magento\Framework\Exception\NoSuchEntityException
	 */
    public function execute()
    {
        if ($this->config->isGigyaEnabled() == false) {
            return parent::execute();
        }

        $loginData = $this->getRequest()->getParam('login_data');
        $remember = $this->getRequest()->getParam('remember');
        $remember = $remember == 'true' ? 1 : 0;

        $this->logger->debug(
            'Login customer: ' .
            'remember: ' . $remember .
            'login data: ' . $this->jsonSerializer->serialize($loginData)
        );

        /** @var \Magento\Framework\Stdlib\Cookie\PublicCookieMetadata $rememberCookieMetadata */
        $rememberCookieMetadata = $this->publicCookieMetadataFactory->create();
        $rememberCookieMetadata->setPath('/');
        $this->cookieManager->setPublicCookie('remember', $remember, $rememberCookieMetadata);
        $this->config->setRemember($remember);

		$validGigyaUser = false;

        try {
        	$validGigyaUser = $this->gigyaMageHelper->getGigyaAccountDataFromLoginData($loginData);
		} catch (GSApiException $e) {
			$this->logger->debug('Gigya returned an error when validating the user. It is possible that there is a problem with the Gigya credentials configured on the site. Error details: ' . $e->getLongMessage());
			throw $e;
		} catch (\Exception $e) {
        	$this->logger->debug('There was an error validating the user. Error: ' . $e->getMessage());
		}

        $responseObject = $this->doLogin($validGigyaUser);

        if (strpos(strtolower($this->getRequest()->getHeader('Accept')), 'json') !== false) {
            $response = $this->resultJsonFactory->create();
            $response->setData($this->extractDataFromDataObject($responseObject));
        } else {
            $response = $this->extractResponseFromDataObject($responseObject);
        }

        $this->applyCookies();
        $this->extendModel->setupSessionCookie();
        $this->applyMessages();

        return $response;
    }

    /**
     * @param \Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser $valid_gigya_user
	 *
     * @return DataObject
     */
    protected function doLogin(GigyaUser $valid_gigya_user)
    {
        $this->logger->debug('Logging in with valid Gigya user: ' . $this->jsonSerializer->serialize($valid_gigya_user));
		$resultRedirect = $this->resultRedirectFactory->create();

        /* If gigya user not validated return error */
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
                    $redirect = $this->encapsulateResponse(
                        $this->accountRedirect->getRedirect(), ['login_successful' => $loginSuccess]
                    );
                } else {
                    $redirect = $this->gigyaCreateUser($resultRedirect, $valid_gigya_user);
                }

                /* Dispatch gigya login event (post-login hook) */
                $this->_eventManager->dispatch('gigya_post_user_login', [
                    "gigya_user" => $valid_gigya_user,
                    "customer" => $customer,
                    "accountManagement" => $this->customerAccountManagement
                ]);
            } catch(\Exception $e) {
                $this->addError($e->getMessage());
                $redirect = $this->encapsulateResponse($this->accountRedirect->getRedirect());
                $defaultUrl = $this->urlModel->getUrl('customer/login', ['_secure' => true]);
            }

            return $redirect;
        }
    }

	/**
	 * Retrieve success message
	 *
	 * @return string
	 *
	 * @throws \Magento\Framework\Exception\NoSuchEntityException
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

            $customer = $this->customerAccountManagement
                ->createAccount($customer, $password, $redirectUrl);

            if ($this->getRequest()->getParam('is_subscribed', false)) {
                $this->subscriberFactory->create()->subscribeCustomerById($customer->getId());
            }

            $this->logger->debug('New Magento customer successfully created');

            $this->_eventManager->dispatch(
                'customer_register_success',
                ['account_controller' => $this, 'customer' => $customer]
            );

            $this->gigyaMageHelper->setSessionExpirationCookie();
            $confirmationStatus = $this->customerAccountManagement->getConfirmationStatus($customer->getId());

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
            'response_object' => (is_string($url) ?
                $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setUrl($url) :
                (is_object($url) ? $url : null)),
            'response_data' => $additionalData
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
            'response_object' => $resultRedirect,
            'response_data' => $additionalData
        ]);
    }

    /**
     * @param DataObject $object
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\Result\Forward $resultRedirect
     */
    protected function extractResponseFromDataObject(DataObject $object)
    {
        return $object->getData('response_object');
    }

    /**
     * @param DataObject $object
     * @return array
     */
    protected function extractDataFromDataObject(DataObject $object)
    {
        return $object->getData('response_data');
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
        return $this->getCookie('gig_login_retry', 0) >= 3;
    }

    /**
     * @return $this
     */
    protected function incrementLoginRetryCounter()
    {
        return $this->setCookie('gig_login_retry', (int) $this->getCookie('gig_login_retry', 0)+1);
    }

    /**
     * @return $this
     */
    protected function deleteLoginRetryCounter()
    {
        $this->cookiesToDelete['gig_login_retry'] = true;
        if(isset($this->cookies['gig_login_retry']))
        {
            unset($this->cookies['gig_login_retry']);
        }
        return $this;
    }

	/**
	 * @return $this
	 *
	 * @throws CookieSizeLimitReachedException
	 * @throws FailureToSendException
	 * @throws InputException
	 * @throws \Magento\Framework\Exception\NoSuchEntityException
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

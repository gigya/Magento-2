<?php

namespace Gigya\GigyaIM\Controller\Raas\Automatic;

use Gigya\GigyaIM\Controller\Raas\AbstractLogin;
use Gigya\GigyaIM\Logger\Logger;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\DataObject;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
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
use Gigya\GigyaIM\Helper\Automatic\Login as LoginHelper;
use Gigya\GigyaIM\Model\Session\Extend;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;

class Login extends AbstractLogin
{
    /**
     * @var LoginHelper
     */
    protected $loginHelper;

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
     * @param Validator $formKeyValidator
     * @param CookieManagerInterface $cookieManager
     * @param GigyaMageHelper $gigyaMageHelper
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param LoginHelper $loginHelper
     * @param Extend $extendModel
	 * @param JsonFactory $jsonFactory
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
        CookieManagerInterface $cookieManager,
        GigyaMageHelper $gigyaMageHelper,
        CookieMetadataFactory $cookieMetadataFactory,
        LoginHelper $loginHelper,
        Extend $extendModel,
		JsonFactory $jsonFactory
    )
    {
        parent::__construct(
            $context,
            $customerSession,
            $scopeConfig,
            $storeManager,
            $accountManagement,
            $addressHelper,
            $urlFactory,
            $formFactory,
            $subscriberFactory,
            $regionDataFactory,
            $addressDataFactory,
            $customerDataFactory,
            $customerUrl,
            $registration,
            $escaper,
            $customerExtractor,
            $dataObjectHelper,
            $accountRedirect,
            $customerRepository,
            $syncHelper,
            $formKeyValidator,
            $cookieManager,
            $gigyaMageHelper,
            $cookieMetadataFactory,
            $extendModel,
			$jsonFactory
        );

        $this->loginHelper = $loginHelper;
    }

	/**
	 * Dispatch request
	 *
	 * @return ResponseInterface|\Magento\Framework\Controller\ResultInterface|mixed
	 *
	 * @throws InputException
	 * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
	 * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
	 * @throws \Zend_Json_Exception
	 */
	public function execute()
	{
	    $this->logger->debug('Performing automatic login');

		if ($this->session->isLoggedIn() || !$this->registration->isAllowed()) {
		    $this->logger->debug(
		        'Will not perform customer login: ' .
                'customer is logged in: ' . ($this->session->isLoggedIn() ? 'true' : 'false') . ', ' .
                'is registration allowed: ' . ($this->registration->isAllowed() ? 'true' : 'false')
            );

			return $this->getJsonResponse(0);
		} else {
			$loginData = $this->getRequest()->getParam('login_data');
			$loginDataObject = \Zend_Json_Decoder::decode($loginData);
			$guid = isset($loginDataObject['UID']) ? $loginDataObject['UID'] : '';
			$request = $this->getRequest();

			if ($this->formKeyValidator->validate($request) and $this->loginHelper->validateAutoLoginParameters($request)) {
				try {
					$this->session->regenerateId();
					$this->extendModel->extendSession(false);

					$this->logger->debug(
					    'Will get Gigya Account from login data: ' . $this->jsonSerializer->serialize($loginData)
                    );

					$valid_gigya_user = $this->gigyaMageHelper->getGigyaAccountDataFromLoginData($loginData);
					$this->doLogin($valid_gigya_user);

					return $this->getJsonResponse($this->session->isLoggedIn());
				} catch (\Exception $e) {
					$this->logger->debug(sprintf('User UID=%s logged to Gigya: %s', $guid,
						\Zend_Date::now()->getIso()));

					return $this->getJsonResponse(0, $e->getMessage());
				}
			} else {
				$this->logger->debug(sprintf('User UID=%s logged to Gigya: %s', $guid, \Zend_Date::now()->getIso()));

				return $this->getJsonResponse(0, __('Invalid Form Key'));
			}
		}
	}

	/**
	 * Return a JSON response
	 *
	 * @param $doReload
	 * @param string $errorMessage
	 *
	 * @return mixed
	 *
	 * @throws InputException
	 * @throws \Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException
	 * @throws \Magento\Framework\Stdlib\Cookie\FailureToSendException
	 */
    protected function getJsonResponse($doReload, $errorMessage = '')
    {
        if($doReload)
        {
            if($errorMessage)
            {
                $this->messageManager->addErrorMessage($errorMessage);
            }
        }
        $this->applyCookies();
        return $this->resultFactory
            ->create(ResultFactory::TYPE_JSON)
            ->setData([
                'reload' => $doReload,
                'logged_in' => $this->session->isLoggedIn() ? 1 : 0
            ]);
    }
}
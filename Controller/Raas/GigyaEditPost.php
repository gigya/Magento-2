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
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\InputException;
use Gigya\GigyaIM\Model\Config as GigyaConfig;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Framework\Controller\Result\JsonFactory as ResultJsonFactory;

class GigyaEditPost extends \Magento\Customer\Controller\Account\EditPost
{
    /** @var AccountManagementInterface */
    protected $customerAccountManagement;

    /** @var CustomerRepositoryInterface */
    protected $customerRepository;

    /** @var Validator */
    protected $formKeyValidator;

    /** @var CustomerExtractor */
    protected $customerExtractor;

    /** @var Session */
    protected $session;

    /** @var GigyaMageHelper */
    protected $gigyaMageHelper;

    /** @var  GigyaSyncHelper */
    protected $gigyaSyncHelper;

    /** @var GigyaConfig */
    protected $config;

    /** @var GigyaLogger */
    protected $logger;

    /**
     * @var ResultJsonFactory
     */
    protected $resultJsonFactory;

	/**
	 * @param Context                     $context
	 * @param Session                     $customerSession
	 * @param AccountManagementInterface  $customerAccountManagement
	 * @param CustomerRepositoryInterface $customerRepository
	 * @param GigyaSyncHelper             $gigyaSyncHelper
	 * @param Validator                   $formKeyValidator
	 * @param CustomerExtractor           $customerExtractor
	 * @param GigyaConfig                 $config
	 * @param GigyaMageHelper             $gigyaMageHelper
	 * @param GigyaLogger                 $logger
	 * @param ResultJsonFactory           $resultJsonFactory
	 */
    public function __construct(
        Context $context,
        Session $customerSession,
        AccountManagementInterface $customerAccountManagement,
        CustomerRepositoryInterface $customerRepository,
        GigyaSyncHelper $gigyaSyncHelper,
        Validator $formKeyValidator,
        CustomerExtractor $customerExtractor,
        GigyaConfig $config,
        GigyaMageHelper $gigyaMageHelper,
		GigyaLogger $logger,
        ResultJsonFactory $resultJsonFactory
    )
    {
        parent::__construct(
            $context,
            $customerSession,
            $customerAccountManagement,
            $customerRepository,
            $formKeyValidator,
            $customerExtractor
        );

        $this->gigyaMageHelper = $gigyaMageHelper;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->config = $config;
        $this->logger = $logger;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * Edit profile and change customer password action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        if ($this->config->isGigyaEnabled() == false) {
            return parent::execute();
        }

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $resultJson->setData(['location' => $this->_url->getUrl('*/*/edit')]);
            return $resultJson;
        }

        if ($this->getRequest()->isPost()) {
            $customerId = $this->session->getCustomerId();
            $currentCustomer = $this->customerRepository->getById($customerId);

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

            try {
                $gigyaAccount = $this->gigyaMageHelper->getGigyaAccountDataFromLoginData($this->getRequest()->getParam('gigya_user'));

                if ($gigyaAccount == false || $gigyaAccount->getUID() != $this->session->getGigyaAccountData()->getUID()) {
                    throw new InputException(__("Could not validate the given Gigya data"));
                }

                $eligibleCustomer = $this->gigyaSyncHelper->setMagentoLoggingContext($gigyaAccount);

                if ($eligibleCustomer == null || $eligibleCustomer->getId() != $customerId) {
                    throw new InputException(__("Could not retrieve a valid Magento customer with the given Gigya data"));
                }

                $this->gigyaMageHelper->transferAttributes($customer, $eligibleCustomer);

                $this->customerRepository->save($eligibleCustomer);
            } catch (AuthenticationException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (InputException $e) {
                $message = __('Invalid input') . $e->getMessage();
                $this->messageManager->addErrorMessage($message);
            } catch (\Exception $e) {
                $message = __('We can\'t save the customer. ') . $e->getMessage();
                $this->messageManager->addErrorMessage($message);
            }

            if ($this->messageManager->getMessages()->getCount() > 0) {
                $this->session->setCustomerFormData($this->getRequest()->getPostValue());

                $resultJson->setData(['location' => $this->_url->getUrl('*/*/edit')]);
                return $resultJson;
            }

            $this->messageManager->addSuccessMessage(__('You saved the account information.'));

            $resultJson->setData(['location' => $this->_url->getUrl('customer/account')]);
            return $resultJson;
        }

        $resultJson->setData(['location' => $this->_url->getUrl('*/*/edit')]);
        return $resultJson;
    }
}

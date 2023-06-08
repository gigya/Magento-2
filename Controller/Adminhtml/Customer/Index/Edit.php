<?php

namespace Gigya\GigyaIM\Controller\Adminhtml\Customer\Index;

use Exception;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Gigya\GigyaIM\Helper\RetryGigyaSyncHelper;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Customer\Helper\View;
use Magento\Customer\Model\Address\Mapper;
use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Metadata\FormFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Math\Random;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Newsletter\Model\SubscriberFactory;

/**
 * Edit
 *
 * Display message(s) related to Gigya synchronizing status when loading and / or saving a Customer detail page from backend.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class Edit extends \Magento\Customer\Controller\Adminhtml\Index\Edit
{
    /** @var  int */
    protected int $customerId;

    /** @var RetryGigyaSyncHelper */
    protected RetryGigyaSyncHelper $retryGigyaSyncHelper;

    /** @var GigyaLogger */
    protected GigyaLogger $logger;

    /**
     * @inheritdoc
     *
     * @param RetryGigyaSyncHelper $retryGigyaSyncHelper
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        FileFactory $fileFactory,
        CustomerFactory $customerFactory,
        AddressFactory $addressFactory,
        FormFactory $formFactory,
        SubscriberFactory $subscriberFactory,
        View $viewHelper,
        Random $random,
        CustomerRepositoryInterface $customerRepository,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        Mapper $addressMapper,
        AccountManagementInterface $customerAccountManagement,
        AddressRepositoryInterface $addressRepository,
        CustomerInterfaceFactory $customerDataFactory,
        AddressInterfaceFactory $addressDataFactory,
        \Magento\Customer\Model\Customer\Mapper $customerMapper,
        DataObjectProcessor $dataObjectProcessor,
        DataObjectHelper $dataObjectHelper,
        DataObjectFactory $objectFactory,
        LayoutFactory $layoutFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        PageFactory $resultPageFactory,
        ForwardFactory $resultForwardFactory,
        JsonFactory $resultJsonFactory,
        RetryGigyaSyncHelper $retryGigyaSyncHelper,
        GigyaLogger $logger
    ) {
        parent::__construct(
            $context,
            $coreRegistry,
            $fileFactory,
            $customerFactory,
            $addressFactory,
            $formFactory,
            $subscriberFactory,
            $viewHelper,
            $random,
            $customerRepository,
            $extensibleDataObjectConverter,
            $addressMapper,
            $customerAccountManagement,
            $addressRepository,
            $customerDataFactory,
            $addressDataFactory,
            $customerMapper,
            $dataObjectProcessor,
            $dataObjectHelper,
            $objectFactory,
            $layoutFactory,
            $resultLayoutFactory,
            $resultPageFactory,
            $resultForwardFactory,
            $resultJsonFactory
        );

        $this->retryGigyaSyncHelper = $retryGigyaSyncHelper;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     *
     * Store the customerId
     */
    protected function initCurrentCustomer()
    {
        $this->customerId = (int)$this->getRequest()->getParam('id');
        if ($this->customerId) {
            $this->_coreRegistry->register(RegistryConstants::CURRENT_CUSTOMER_ID, $this->customerId);
        }

        return $this->customerId;
    }

    /**
     * @inheritdoc
     *
     * Display an appropriate status message relative to the Gigya synchronizing process :
     * . when customer page is loaded we have to tell if the Customer entity is up-to-date with the current Gigya profile data, or not (may be the Customer is already concerned by a retry scheduled, or the Gigya service is not reachable)
     * . when it's saved we have to tell if a retry is scheduled due to an error on saving (could be a Gigya service call failure as well as a Magento update failure)
     *
     * @return \Magento\Backend\Model\View\Result\Page|\Magento\Backend\Model\View\Result\Redirect|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        try {
            $result = parent::execute();

            if ($this->customerId) {
                $retryG2CMSCount = $this->retryGigyaSyncHelper->getCurrentRetryCount(RetryGigyaSyncHelper::ORIGIN_GIGYA, $this->customerId);
                $retryCMS2GCount = $this->retryGigyaSyncHelper->getCurrentRetryCount(RetryGigyaSyncHelper::ORIGIN_CMS, $this->customerId);

                if ($retryG2CMSCount == -1) {
                    if ($retryCMS2GCount == -1) {
                        $customer = $this->_customerRepository->getById($this->customerId);
                        if ($customer->getCustomAttribute('gigya_account_enriched')->getValue() === true) {
                            $this->messageManager->addSuccessMessage(__('Data is up-to-date with Gigya account.'));
                        } else {
                            $this->messageManager->addWarningMessage(__('Data synchronizing from Gigya is impossible. The data could be outdated, please come back later.'));
                        }
                    }
                } elseif ($retryG2CMSCount == 0) {
                    $this->messageManager->addWarningMessage(__('Data is not synchronized to Gigya account, retrying in progress.'));
                } elseif ($retryG2CMSCount > 0) {
                    $this->messageManager->addWarningMessage(__('Retry data synchronizing to Gigya failed. Please wait for next retry or try to update the data again.'));
                }

                if ($retryCMS2GCount == 0) {
                    $this->messageManager->addWarningMessage(__('Data is not synchronized to Gigya account, retrying in progress.'));
                } elseif ($retryCMS2GCount > 0) {
                    $this->messageManager->addWarningMessage(__('Retry data synchronizing to Gigya failed. Please wait for next retry or try to update the data again.'));
                }

                $result->getLayout()->initMessages();
            }

            return $result;
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/*/index');
            return $resultRedirect;
        }
    }
}

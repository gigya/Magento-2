<?php
/**
 * Copyright © 2016 X2i.
 */

namespace Gigya\GigyaIM\Controller\Adminhtml\Customer\Index;

use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Helper\GigyaSyncRetryHelper;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Data\ObjectFactory;
use Magento\Framework\DataObjectFactory;

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
    protected $customerId;

    /** @var GigyaSyncRetryHelper */
    protected $gigyaSyncRetryHelper;

    /**
     * @inheritdoc
     *
     * @param GigyaSyncRetryHelper $gigyaSyncRetryHelper
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Customer\Model\Metadata\FormFactory $formFactory,
        \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory,
        \Magento\Customer\Helper\View $viewHelper,
        \Magento\Framework\Math\Random $random,
        CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\Api\ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        \Magento\Customer\Model\Address\Mapper $addressMapper,
        AccountManagementInterface $customerAccountManagement,
        AddressRepositoryInterface $addressRepository,
        CustomerInterfaceFactory $customerDataFactory,
        AddressInterfaceFactory $addressDataFactory,
        \Magento\Customer\Model\Customer\Mapper $customerMapper,
        \Magento\Framework\Reflection\DataObjectProcessor $dataObjectProcessor,
        DataObjectHelper $dataObjectHelper,
        DataObjectFactory $objectFactory,
        \Magento\Framework\View\LayoutFactory $layoutFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        GigyaSyncRetryHelper $gigyaSyncRetryHelper
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

        $this->gigyaSyncRetryHelper = $gigyaSyncRetryHelper;
    }

    /**
     * @inheritdoc
     *
     * Store the customerId
     */
    protected function initCurrentCustomer()
    {
        $this->customerId = parent::initCurrentCustomer();

        return $this->customerId;
    }

    public function execute()
    {
        try {
            $result = parent::execute();

            if ($this->customerId) {

                $retryCount = $this->gigyaSyncRetryHelper->getCurrentRetryCount($this->customerId);

                if ($retryCount == -1) {
                    /** @var CustomerInterface $customer */
                    $customer = $this->_customerRepository->getById($this->customerId);
                    if ($customer->getCustomAttribute('gigya_account_enriched')->getValue() === true) {
                        $this->messageManager->addSuccessMessage(__('Data are up-to-date with Gigya account.'));
                    } else {
                        $this->messageManager->addWarningMessage(__('Connection with Gigya is impossible. The data could be outdated, please come back later.'));
                    }
                } else if ($retryCount == 0) {
                    $this->messageManager->addWarningMessage(__('Data are not synchronized to Gigya account, retrying in progress.'));
                } else if ($retryCount > 0) {
                    $this->messageManager->addWarningMessage(__('Data synchronizing to Gigya failed failed. Please try to update the data again.'));
                }

                $result->getLayout()->initMessages();
            }

            return $result;
        } catch (GigyaFieldMappingException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/*/index');
            return $resultRedirect;
        }
    }
}
<?php

namespace Gigya\GigyaIM\Plugin\Customer\ResourceModel;

use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Event\Manager;

class RepositoryAdditionalEvent
{
    /**
     * @var Manager
     */
    protected $eventManager;

    /**
     * @var ExtensibleDataObjectConverter
     */
    protected $extensibleDataObjectConverter;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var AddressFactory
     */
    protected $addressFactory;

    /**
     * @param Manager $eventManager
     * @param CustomerFactory $customerFactory
     * @param ExtensibleDataObjectConverter $extensibleDataObjectConverter
     * @param AddressFactory $addressFactory
     */
    public function __construct(
        Manager $eventManager,
        CustomerFactory $customerFactory,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        AddressFactory $addressFactory
    )
    {
        $this->eventManager = $eventManager;
        $this->extensibleDataObjectConverter = $extensibleDataObjectConverter;
        $this->customerFactory = $customerFactory;
        $this->addressFactory = $addressFactory;
    }

    /**
     * @param \Magento\Customer\Model\ResourceModel\CustomerRepository $subject
     * @param \Closure $proceed
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param null $passwordHash
     */
    public function aroundSave(
        \Magento\Customer\Model\ResourceModel\CustomerRepository $subject, \Closure $proceed,
        \Magento\Customer\Api\Data\CustomerInterface $customer, $passwordHash = null)
    {
        try
        {
            $customerClone = clone $customer;

            $addresses = $customerClone->getAddresses();
            $customerClone->setAddresses([]);
            $customerData = $this->extensibleDataObjectConverter->toNestedArray(
                $customerClone,
                [],
                '\Magento\Customer\Api\Data\CustomerInterface'
            );
            /* @var $customerModel \Magento\Customer\Model\Customer */
            $customerModel = $this->customerFactory->create(['data' => $customerData]);
            if(isset($customerData['id']))
            {
                $customerModel->setId($customerData['id']);
            }

            $this->eventManager->dispatch('customer_save_before_with_dataobject', [
                'customer' => $customerModel, 'object' => $customerModel, 'customer_data' => $customer
            ]);

            return $proceed($customer, $passwordHash);
        }
        catch(\Exception $e)
        {
            throw $e;
        }
    }
}
<?php
/*
 * Register a new customer from Raas
 * accept user params from Raas result
 * create new customer / return creation error message
 * log in the customer and reload
 * http://magento.stackexchange.com/questions/78164/how-to-add-a-customer-programmatically-in-magento-2
 */
namespace Gigya\GigyaM2\Controller\Raas;
class User extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @param \Magento\Framework\App\Action\Context      $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Customer\Model\CustomerFactory    $customerFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\AccountManagement $accountManagement
    ) {
        $this->storeManager     = $storeManager;
        $this->customerFactory  = $customerFactory;
        $this->accountManagement = $accountManagement;

        parent::__construct($context);
    }

    public function execute()
    {
        // $resultRedirect = $this->resultRedirectFactory->create();
        // Get Website ID
        $websiteId  = $this->storeManager->getWebsite()->getWebsiteId();

        // Instantiate object (this is the most important part)
        $customer   = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);

        // Preparing data for new customer
        $customer->setEmail("test2345@gmail.com");
        $customer->setFirstname("First Name");
        $customer->setLastname("Last name");
        $customer->setPassword("password");

        // Save data
        // AccountManagement:
        // createAccountWithPasswordHash
        $customer->save();
        $id = $customer->getId();
    //    $customer->sendNewAccountEmail();
        // AccountManagement:545:
        // $this->sendEmailConfirmation($customer, $redirectUrl);

        // createAccountWithPasswordHash :
        // $resultRedirect = $this->accountRedirect->getRedirect();
        echo ("customer create output");
        var_dump($customer);
    }
}
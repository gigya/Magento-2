<?php
/**
 * Clever-Age
 * Date: 11/05/17
 * Time: 11:19
 */

namespace Gigya\GigyaIM\Helper;

use Gigya\CmsStarterKit\sdk\GSException;
use Gigya\CmsStarterKit\user\GigyaUser;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context as HelperContext;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session;

class GigyaSyncHelper extends AbstractHelper
{
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /** @var CustomerRepositoryInterface */
    protected $customerRepository;

    /** @var SearchCriteriaBuilder */
    protected $searchCriteriaBuilder;

    /** @var FilterBuilder */
    protected $filterBuilder;

    /** @var  FilterGroupBuilder */
    protected $filterGroupBuilder;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Session
     */
    protected $session;

    /**
     * GigyaSyncHelper constructor.
     *
     * @param HelperContext $helperContext
     * @param MessageManager $messageManager
     * @param CustomerRepositoryInterface $customerRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param StoreManagerInterface $storeManager
     * @param Session $customerSession
     */
    public function __construct(
        HelperContext $helperContext,
        MessageManager $messageManager,
        CustomerRepositoryInterface $customerRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        StoreManagerInterface $storeManager,
        Session $customerSession
    )
    {
        parent::__construct($helperContext);
        $this->messageManager = $messageManager;
        $this->customerRepository =$customerRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->storeManager = $storeManager;
        $this->session = $customerSession;
    }

    /**
     * Given a GigyaUser built with the data returned by the Gigya's RaaS service, this method will validate its concordance with the Magento accounts and if ok set the Gigya account data on session.
     *
     * The Gigya account furnished will be set on session variable 'gigya_logged_in_account' : get it with Magento\Customer\Model\Session::getGigyaLoggedInAccount()
     * The email associated for the Magento account will be set on session variable 'gigya_logged_in_email' : get it with Magento\Customer\Model\Session::getGigyaLoggedInEmail()
     *
     * Whenver an error occurs or an exception is thrown within this method those session variables are set to null.
     *
     * @param GigyaUser $gigyaAccount The data furnished by the Gigya RaaS service.
     * @return CustomerInterface $customer If not null it's the existing Magento customer account that will be used for logging. Otherwise it means that a Magento customer account should be created with the email set on session variable.
     *
     * @throws GSException If no Magento customer account could be used nor created with this Gigya UID and provided LoginIDs emails : user can not be logged in.
     *                     Reason can be : all emails attached with this Gigya account are already set on Magento accounts on this website but for other Gigya UIDs.
     */
    public function setGigyaAccountOnSession($gigyaAccount)
    {
        // This value will be set with the preferred email that should be attached with the Magento customer account, among all the Gigya loginIDs emails
        // We initialize it to null. If it's still null at the end of the algorithm that means that the user can not logged in
        // because all Gigya loginIDs emails are already set to existing Magento customer accounts with a different or null Gigya UID
        $this->session->setGigyaLoggedInEmail(null);
        // This will be set with the incoming $gigyaAccount parameter if the customer can be logged in on Magento.
        $this->session->setGigyaLoggedInAccount(null);

        $gigyaUid = $gigyaAccount->getUID();
        $gigyaLoginIdsEmails = $gigyaAccount->getLoginIDs()['emails'];
        $gigyaProfileEmail = $gigyaAccount->getProfile()->getEmail();
        /** @var CustomerInterface $magentoCustomer */
        $magentoCustomer = null;
        // Will be fed with the emails that are already used by a Magento customer account, but to a different or null Gigya UID
        $notUsableEmails = [];
        // Search criteria and filter to use for checking the existence of a Magento customer account with a given email
        $searchCustomerByEmailCriteriaFilter = $this->filterBuilder->setField('email')->setConditionType('eq')->create();
        $searchCustomerByEmailCriteria = $this->searchCriteriaBuilder->addFilter($searchCustomerByEmailCriteriaFilter)->create();

        // 0. search for existing Magento accounts with Gigya loginIDs emails...
        $filter = $this->filterBuilder->setConditionType('in')->setField('email')->setValue($gigyaLoginIdsEmails)->create();
        $filterGroups[] = $this->filterGroupBuilder->addFilter($filter)->create();
        $filter = $this->filterBuilder->setConditionType('eq')->setField('website_id')->setValue($this->storeManager->getStore()->getWebsiteId())->create();
        $filterGroups[] = $this->filterGroupBuilder->addFilter($filter)->create();
        $searchCriteria = $this->searchCriteriaBuilder->create()->setFilterGroups($filterGroups);
        $searchResult = $this->customerRepository->getList($searchCriteria);
        // ...and among these, check if one is set to the Gigya UID
        foreach ($searchResult->getItems() as $customer) {
            $magentoUid = $customer->getCustomAttribute('gigya_uid')->getValue();
            if ($magentoUid === $gigyaUid) {
                $magentoCustomer = $customer;
            } else {
                $notUsableEmails[] = $customer->getEmail();
            }
        }

        if ($magentoCustomer != null) {
            // 1. customer account exists on Magento with this Gigya UID : check if we should update his email with the Gigya profile email
            $updateMagentoCustomerWithGigyaProfileEmail = false;
            // Gigya profile email is in the Gigya loginIDs emails ?
            if (in_array($gigyaProfileEmail, $gigyaLoginIdsEmails)) {
                // and customer email is not the Gigya profile email ?
                if ($magentoCustomer->getEmail() != $gigyaProfileEmail) {
                    // and Gigya profile email is not already attached to an existing Magento account ?
                    $searchCustomerByEmailCriteriaFilter->setValue($gigyaProfileEmail);
                    if ($this->customerRepository->getList($searchCustomerByEmailCriteria)->getTotalCount() == 0) {
                        $updateMagentoCustomerWithGigyaProfileEmail = true;
                    }
                }
            }

            if ($updateMagentoCustomerWithGigyaProfileEmail) {
                $this->session->setGigyaLoggedInEmail($gigyaProfileEmail);
            } else {
                $this->session->setGigyaLoggedInEmail($magentoCustomer->getEmail());
            }
        } else {
            // 2. no customer account exists on Magento with this Gigya UID and one of the Gigya loginIDs emails : check if we can create on with one of the Gigya loginIDs emails
            // 2.1 Gigya profile email is the preferred one
            $updateMagentoCustomerWithGigyaProfileEmail = false;
            // Gigya profile email is in the Gigya loginIDs emails ?
            if (in_array($gigyaProfileEmail, $gigyaLoginIdsEmails)) {
                // and Gigya profile email is not already attached to an existing Magento account ?
                $searchCustomerByEmailCriteriaFilter->setValue($gigyaProfileEmail);
                if ($this->customerRepository->getList($searchCustomerByEmailCriteria)->getTotalCount() == 0) {
                    $updateMagentoCustomerWithGigyaProfileEmail = true;
                }
            }

            if ($updateMagentoCustomerWithGigyaProfileEmail) {
                $this->session->setGigyaLoggedInEmail($gigyaProfileEmail);
            } else {
                // 2.2 Gigya profile email can not be used for the new Magento account : we check if we can use one of the other Gigya loginIDs emails
                foreach ($gigyaLoginIdsEmails as $gigyaLoginIdEmail) {
                    // this email is not already attached on a Magento customer account with another or null Gigya UID ?
                    if (!in_array($gigyaLoginIdEmail, $notUsableEmails)) {
                        $this->session->setGigyaLoggedInEmail($gigyaLoginIdEmail);
                        break;
                    }
                }
            }
        }

        // No Gigya loginIDs email could be used to identify an existing Magento account, or to create a new Magento account : exception
        if ($this->session->getGigyaLoggedInEmail() == null) {
            throw new GSException(__('Email already exists'));
        } else {
            $this->session->setGigyaLoggedInAccount($gigyaAccount);
        }

        return $magentoCustomer;
    }

    /**
     * Update the required fields of a Magento customer model with the current logged in Gigya account data.
     *
     * The concerned fields are :
     * . gigya_uid
     * . email
     * . first_name
     * . last_name
     *
     * For other fields see // CATODO => field mapping
     *
     * @param Customer $magentoCustomer
     * @param GigyaUser $gigyaAccount
     * @param string $gigyaLoggedInEmail
     * @return void
     */
    public function updateMagentoCustomerWithGygiaAccount($magentoCustomer, $gigyaAccount, $gigyaLoggedInEmail)
    {
        $magentoCustomer->setGigyaUid($gigyaAccount->getUID());
        $magentoCustomer->setEmail($gigyaLoggedInEmail);
        $magentoCustomer->setFirstname($gigyaAccount->getProfile()->getFirstName());
        $magentoCustomer->setLastname($gigyaAccount->getProfile()->getLastName());
    }

    /**
     * Update the required fields of a Magento customer interface with the current logged in Gigya account data (those data are stored on session : no call to Gigya is made here)
     *
     * The concerned fields are :
     * . gigya_uid
     * . email
     * . first_name
     * . last_name
     *
     * For other fields see // CATODO => field mapping
     *
     * @param CustomerInterface $magentoCustomerData
     * @param GigyaUser $gigyaAccount
     * @param string $gigyaLoggedInEmail
     * @return void
     */
    public function updateMagentoCustomerDataWithSessionGygiaAccount($magentoCustomerData, $gigyaAccount, $gigyaLoggedInEmail)
    {
        $magentoCustomerData->setCustomAttribute('gigya_uid',$gigyaAccount->getUID());
        $magentoCustomerData->setEmail($gigyaLoggedInEmail);
        $magentoCustomerData->setFirstname($gigyaAccount->getProfile()->getFirstName());
        $magentoCustomerData->setLastname($gigyaAccount->getProfile()->getLastName());
    }

    /**
     * Update the required fields of the current logged in Gigya account data with a Magento customer interface (that concerns the data stored on session : no call to Gigya is made here)
     *
     * For other fields see // CATODO => field mapping
     *
     * @param GigyaUser $gigyaAccount
     * @param CustomerInterface $magentoCustomerData
     * @return void
     */
    public function updateSessionGygiaAccountWithMagentoCustomerData($gigyaAccount, $magentoCustomerData)
    {
        $gigyaAccount->getProfile()->setFirstName($magentoCustomerData->getFirstname());
        $gigyaAccount->getProfile()->setLastName($magentoCustomerData->getLastname());
    }
}

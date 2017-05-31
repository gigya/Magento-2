<?php
/**
 * Clever-Age
 * Date: 11/05/17
 * Time: 11:19
 */

namespace Gigya\GigyaIM\Helper;

use Gigya\CmsStarterKit\sdk\GSException;
use Gigya\CmsStarterKit\user\GigyaProfile;
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
    const DIR_BOTH = 'both';
    const DIR_G2CMS = 'g2cms';
    const DIR_CMS2G = 'cms2g';

    /**
     * @var array
     */
    protected $productIdsExcludedFromSync = [];

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
        $this->productIdsExcludedFromSync = [
            self::DIR_CMS2G => [], self::DIR_G2CMS => []
        ];
    }

    /**
     * Given a GigyaUser built with the data returned by the Gigya's RaaS service we identify the Magento customer and the eligible email for logging.
     *
     * @param GigyaUser $gigyaAccount The data furnished by the Gigya RaaS service.
     * @return array [
     *                  'customer' => CustomerInterface If not null it's the existing Magento customer account that shall be used for Magento logging. Otherwise it means that a Magento customer account should be created with the 'logging_email'.
     *                  'logging_email' => string The email to set on the Magento customer account.
     *               ]
     *
     * @throws GSException If no Magento customer account could be used nor created with this Gigya UID and provided LoginIDs emails : user can not be logged in.
     *                     Reason can be for instance : all emails attached with this Gigya account are already set on Magento accounts on this website but for other Gigya UIDs.
     */
    public function getMagentoCustomerAndLoggingEmail($gigyaAccount)
    {
        /** @var CustomerInterface $magentoLoggingCustomer */
        $magentoLoggingCustomer = null;
        /** @var string $magentoLoggingEmail */
        $magentoLoggingEmail = null;

        $gigyaUid = $gigyaAccount->getUID();
        $gigyaLoginIdsEmails = $gigyaAccount->getLoginIDs()['emails'];
        $gigyaProfileEmail = $gigyaAccount->getProfile()->getEmail();
        // Will be fed with the emails that are already used by a Magento customer account, but to a different or null Gigya UID
        $notUsableEmails = [];
        // Search criteria and filter to use for checking the existence of a Magento customer account with a given email
        $filterGroups = [];
        $searchCustomerByEmailCriteriaFilter = $this->filterBuilder->setField('email')->setConditionType('eq')->create();
        $filterGroups[] = $this->filterGroupBuilder->addFilter($searchCustomerByEmailCriteriaFilter)->create();
        $searchCustomerByWebsiteIdCriteriaFilter = $this->filterBuilder->setField('website_id')->setConditionType('eq')->create();
        $filterGroups[] = $this->filterGroupBuilder->addFilter($searchCustomerByWebsiteIdCriteriaFilter)->create();
        $searchCustomerByEmailCriteria = $this->searchCriteriaBuilder->create()->setFilterGroups($filterGroups);

        // 0. search for existing Magento accounts with Gigya loginIDs emails...
        $filterGroups = [];
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
                $magentoLoggingCustomer = $customer;
            } else {
                $notUsableEmails[] = $customer->getEmail();
            }
        }

        if ($magentoLoggingCustomer != null) {
            // 1. customer account exists on Magento with this Gigya UID : check if we should update his email with the Gigya profile email
            $updateMagentoCustomerWithGigyaProfileEmail = false;
            // Gigya profile email is in the Gigya loginIDs emails ?
            if (in_array($gigyaProfileEmail, $gigyaLoginIdsEmails)) {
                // and customer email is not the Gigya profile email ?
                if ($magentoLoggingCustomer->getEmail() != $gigyaProfileEmail) {
                    // and Gigya profile email is not already attached to an existing Magento account ?
                    $searchCustomerByEmailCriteriaFilter->setValue($gigyaProfileEmail);
                    $searchCustomerByWebsiteIdCriteriaFilter->setValue($this->storeManager->getStore()->getWebsiteId());
                    if ($this->customerRepository->getList($searchCustomerByEmailCriteria)->getTotalCount() == 0) {
                        $updateMagentoCustomerWithGigyaProfileEmail = true;
                    }
                }
            }

            if ($updateMagentoCustomerWithGigyaProfileEmail) {
                $magentoLoggingEmail = $gigyaProfileEmail;
            } else {
                $magentoLoggingEmail = $magentoLoggingCustomer->getEmail();
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
                $magentoLoggingEmail = $gigyaProfileEmail;
            } else {
                // 2.2 Gigya profile email can not be used for the new Magento account : we check if we can use one of the other Gigya loginIDs emails
                foreach ($gigyaLoginIdsEmails as $gigyaLoginIdEmail) {
                    // this email is not already attached on a Magento customer account with another or null Gigya UID ?
                    if (!in_array($gigyaLoginIdEmail, $notUsableEmails)) {
                        $magentoLoggingEmail = $gigyaLoginIdEmail;
                        break;
                    }
                }
            }
        }

        // No Gigya loginIDs email could be used to identify an existing Magento account, or to create a new Magento account : exception
        if ($magentoLoggingEmail == null) {
            throw new GSException(__('Email already exists'));
        }

        return [
            'customer' => $magentoLoggingCustomer,
            'logging_email' => $magentoLoggingEmail
        ];
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
     * @param string $gigyaAccountLoggingEmail
     * @return void
     */
    public function updateMagentoCustomerRequiredFieldsWithGygiaData($magentoCustomer, $gigyaAccount, $gigyaAccountLoggingEmail)
    {
        $magentoCustomer->setGigyaUid($gigyaAccount->getUID());
        $magentoCustomer->setEmail($gigyaAccountLoggingEmail);
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
     * @param CustomerInterface $magentoCustomer
     * @param GigyaUser $gigyaAccount
     * @param string $gigyaLoggingEmail
     * @return void
     */
    public function updateMagentoCustomerDataWithSessionGygiaAccount($magentoCustomer, $gigyaAccount, $gigyaLoggingEmail)
    {
        $magentoCustomer->setCustomAttribute('gigya_uid',$gigyaAccount->getUID());
        $magentoCustomer->setEmail($gigyaLoggingEmail);
        $magentoCustomer->setFirstname($gigyaAccount->getProfile()->getFirstName());
        $magentoCustomer->setLastname($gigyaAccount->getProfile()->getLastName());
    }

    /**
     * @param int $productId
     * @param string $dir
     * @return $this
     */
    public function excludeProductIdFromSync($productId, $dir = self::DIR_BOTH)
    {
        if(in_array($dir, [self::DIR_BOTH, self::DIR_CMS2G]))
        {
            $this->productIdsExcludedFromSync[self::DIR_CMS2G][$productId] = true;
        }
        if(in_array($dir, [self::DIR_BOTH, self::DIR_G2CMS]))
        {
            $this->productIdsExcludedFromSync[self::DIR_G2CMS][$productId] = true;
        }
        return $this;
    }

    /**
     * @param int $productId
     * @param string $dir
     * @return $this
     */
    public function undoExcludeProductIdFromSync($productId, $dir = self::DIR_BOTH)
    {
        if(in_array($dir, [self::DIR_BOTH, self::DIR_CMS2G]))
        {
            $this->productIdsExcludedFromSync[self::DIR_CMS2G][$productId] = false;
        }
        if(in_array($dir, [self::DIR_BOTH, self::DIR_G2CMS]))
        {
            $this->productIdsExcludedFromSync[self::DIR_G2CMS][$productId] = false;
        }
        return $this;
    }

    /**
     * @param int $productId
     * @param string $dir
     * @return bool
     */
    public function isProductIdExcludedFromSync($productId, $dir)
    {
        return isset($this->productIdsExcludedFromSync[$dir][$productId])
            && $this->productIdsExcludedFromSync[$dir][$productId];
    }

}

<?php

namespace Gigya\GigyaIM\Helper;

use Gigya\PHP\GSException;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Config\Share;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Data\Customer as DataCustomer;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context as HelperContext;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\State as AppState;

class GigyaSyncHelper extends AbstractHelper
{
    const DIR_BOTH = 'both';
    const DIR_G2CMS = 'g2cms';
    const DIR_CMS2G = 'cms2g';

    /**
     * @var array
     */
    protected static $customerIdsExcludedFromSync = [ self::DIR_CMS2G => [], self::DIR_G2CMS => [] ];

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

    /** @var  AppState */
    protected $appState;

    /**
     * @var Share
     */
    protected $shareConfig;

	/**
	 * GigyaSyncHelper constructor.
	 *
	 * @param HelperContext               $helperContext
	 * @param MessageManager              $messageManager
	 * @param CustomerRepositoryInterface $customerRepository
	 * @param SearchCriteriaBuilder       $searchCriteriaBuilder
	 * @param FilterBuilder               $filterBuilder
	 * @param FilterGroupBuilder          $filterGroupBuilder
	 * @param StoreManagerInterface       $storeManager
	 * @param Session                     $customerSession
	 * @param AppState                    $state
	 * @param Share                       $shareConfig
	 */
    public function __construct(
        HelperContext $helperContext,
        MessageManager $messageManager,
        CustomerRepositoryInterface $customerRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        StoreManagerInterface $storeManager,
        Session $customerSession,
        AppState $state,
        Share $shareConfig
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
        $this->appState = $state;
        $this->shareConfig = $shareConfig;
    }

	/**
	 * Given a GigyaUser built with the data returned by the Gigya's RaaS service we identify the Magento customer and the eligible email for logging.
	 *
	 * @param GigyaUser $gigyaAccount The data furnished by the Gigya RaaS service.
	 *
	 * @return array [
	 *                  'customer' => CustomerInterface If not null it's the existing Magento customer account that shall be used for Magento logging. Otherwise it means that a Magento customer account should be created with the 'logging_email'.
	 *                  'logging_email' => string The email to set on the Magento customer account.
	 *               ]
	 *
	 * @throws GSException If no Magento customer account could be used nor created with this Gigya UID and provided LoginIDs emails : user can not be logged in.
	 *                     Reason can be for instance : all emails attached with this Gigya account are already set on Magento accounts on this website but for other Gigya UIDs.
	 * @throws \Magento\Framework\Exception\LocalizedException
	 */
    public function getMagentoCustomerAndLoggingEmail($gigyaAccount)
    {
        /** @var CustomerInterface $magentoLoggingCustomer */
        $magentoLoggingCustomer = null;
        /** @var string $magentoLoggingEmail */
        $magentoLoggingEmail = null;

        $gigyaUid = $gigyaAccount->getUID();
        $gigyaEmails = $gigyaLoginIdsEmails = $gigyaAccount->getLoginIDs()['emails'];
        $gigyaProfileEmail = $gigyaAccount->getProfile()->getEmail();
		$gigyaEmails[] = $gigyaProfileEmail;

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
        $filter = $this->filterBuilder->setConditionType('in')->setField('email')->setValue($gigyaEmails)->create();
        $filterGroups[] = $this->filterGroupBuilder->addFilter($filter)->create();
		if ($this->shareConfig->isWebsiteScope()) {
			$filter = $this->filterBuilder->setConditionType('eq')->setField('website_id')->setValue($this->storeManager->getStore()->getWebsiteId())->create();
			$filterGroups[] = $this->filterGroupBuilder->addFilter($filter)->create();
		}
        $searchCriteria = $this->searchCriteriaBuilder->create()->setFilterGroups($filterGroups);
        $searchResult = $this->customerRepository->getList($searchCriteria);
        // ...and among these, check if one is set to the Gigya UID
        foreach ($searchResult->getItems() as $customer) {
            $magentoUid = $customer->getCustomAttribute('gigya_uid') ? $customer->getCustomAttribute('gigya_uid')->getValue() : null;
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
            // 2. no customer account exists on Magento with this Gigya UID and one of the Gigya loginIDs emails : check if we can create it with one of the Gigya loginIDs emails
            // 2.1 Gigya profile email is the preferred one
            $updateMagentoCustomerWithGigyaProfileEmail = false;

            // Gigya profile email is in the Gigya loginIDs emails ?
            if (in_array($gigyaProfileEmail, $gigyaLoginIdsEmails)) {
                // and Gigya profile email is not already attached to an existing Magento account ?
                $searchCustomerByEmailCriteriaFilter->setValue($gigyaProfileEmail);
                $searchCustomerByWebsiteIdCriteriaFilter->setValue($this->storeManager->getStore()->getWebsiteId());
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
     * /!\ This is done only in frontend context : no required fields on other contexts.
     *
     * The concerned fields are :
     * . gigya_uid
     * . email
     * . first_name
     * . last_name
     *
     * For other fields see Gigya\GigyaIM\Model\FieldMapping\GigyaToMagento
     *
     * @param DataCustomer $magentoCustomer
     * @param GigyaUser $gigyaAccount
     * @param string $gigyaAccountLoggingEmail
     * @return void
     */
    public function updateMagentoCustomerRequiredFieldsWithGigyaData($magentoCustomer, $gigyaAccount, $gigyaAccountLoggingEmail)
    {
        try {
            $areaCode = $this->appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $areaCode = null;
        }

        if ($areaCode == Area::AREA_FRONTEND) {

            $magentoCustomer->setGigyaUid($gigyaAccount->getUID());
            $magentoCustomer->setEmail($gigyaAccountLoggingEmail);
            if (empty($magentoCustomer->getFirstname())) {
                $magentoCustomer->setFirstname($gigyaAccount->getProfile()->getFirstName());
            }
            if (empty($magentoCustomer->getLastname())) {
                $magentoCustomer->setLastname($gigyaAccount->getProfile()->getLastName());
            }
        }
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
    public function updateMagentoCustomerDataWithSessionGigyaAccount($magentoCustomer, $gigyaAccount, $gigyaLoggingEmail)
    {
        $magentoCustomer->setCustomAttribute('gigya_uid',$gigyaAccount->getUID());
        $magentoCustomer->setEmail($gigyaLoggingEmail);
        $magentoCustomer->setFirstname($gigyaAccount->getProfile()->getFirstName());
        $magentoCustomer->setLastname($gigyaAccount->getProfile()->getLastName());
    }

    /**
     * For a given Gigya account data : identify which Magento customer account is to be logged in, and the email that shall be set on this account.
     *
     * The Gigya account furnished will be set on session variable 'gigya_account_data' : get it with Magento\Customer\Model\Session::getGigyaAccountData()
     * The email associated for the Magento account will be set on session variable 'gigya_account_logging_email' : get it with Magento\Customer\Model\Session::getGigyaAccountLoggingEmail()
     *
     * @param GigyaUser $gigyaAccount The data returned by the Gigya's service on customer logging or profile edit.
     * @return CustomerInterface The Magento customer linked with this Gigya account (can be null if no account exists yet)
     * @throws @see getMagentoCustomerAndLoggingEmail()
     */
    public function setMagentoLoggingContext($gigyaAccount)
    {
        // This value will be set with the preferred email that should be attached with the Magento customer account, among all the Gigya loginIDs emails
        // We initialize it to null. If it's still null at the end of the algorithm that means that the user can not logged in
        // because all Gigya loginIDs emails are already set to existing Magento customer accounts with a different or null Gigya UID
        $this->session->setGigyaAccountLoggingEmail(null);

        // This will be set with the incoming $gigyaAccount parameter if the customer can be logged in on Magento.
        $this->session->setGigyaAccountData(null);

        $result = $this->getMagentoCustomerAndLoggingEmail($gigyaAccount);

        $this->session->setGigyaAccountData($gigyaAccount);
        $this->session->setGigyaAccountLoggingEmail($result['logging_email']);

        return $result['customer'];
    }

    /**
     * Disable Gigya synchronisation for a selected customer ID.
     *
     * @param int $customerId Customer ID
     * @param string $dir direction: "cms2g", "g2cms" or "both"
     * @return $this
     */
    public function excludeCustomerIdFromSync($customerId, $dir = self::DIR_BOTH)
    {
        if(in_array($dir, [self::DIR_BOTH, self::DIR_CMS2G]))
        {
            self::$customerIdsExcludedFromSync[self::DIR_CMS2G][$customerId] = true;
        }
        if(in_array($dir, [self::DIR_BOTH, self::DIR_G2CMS]))
        {
            self::$customerIdsExcludedFromSync[self::DIR_G2CMS][$customerId] = true;
        }
        return $this;
    }

    /**
     * Re-enable Gigya synchronisation for a selected customer ID.
     *
     * @param int $customerId Customer ID for which excludeCustomerIdFromSync() has been previously run
     * @param string $dir direction: "cms2g", "g2cms" or "both"
     * @return $this
     */
    public function undoExcludeCustomerIdFromSync($customerId, $dir = self::DIR_BOTH)
    {
        if(in_array($dir, [self::DIR_BOTH, self::DIR_CMS2G]))
        {
            self::$customerIdsExcludedFromSync[self::DIR_CMS2G][$customerId] = false;
        }
        if(in_array($dir, [self::DIR_BOTH, self::DIR_G2CMS]))
        {
            self::$customerIdsExcludedFromSync[self::DIR_G2CMS][$customerId] = false;
        }
        return $this;
    }

    /**
     * Check whether Gigya synchronisation is enabled for a customer ID in a particular direction
     *
     * @param int $customerId Customer ID
     * @param string $dir direction: "cms2g" or "g2cms", but not "both"
     * @return bool
     */
    public function isCustomerIdExcludedFromSync($customerId, $dir)
    {
        return isset(self::$customerIdsExcludedFromSync[$dir][$customerId])
            && self::$customerIdsExcludedFromSync[$dir][$customerId];
    }

}

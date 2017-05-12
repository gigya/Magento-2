<?php
/**
 * Clever-Age
 * Date: 11/05/17
 * Time: 11:19
 */

namespace Gigya\GigyaIM\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context as HelperContext;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session;

class GigyaSyncHelper extends AbstractHelper
{
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var \Magento\Store\Model\StoreManagerInterfacee
     */
    protected $storeManager;

    /**
     * @var Session
     */
    protected $session;

    /**
     * GigyaSyncHelper constructor.
     * @param HelperContext $helperContext
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param CustomerRepositoryInterface $customerRepository
     * @param StoreManagerInterface $storeManager
     * @param Session $customerSession
     */
    public function __construct(
        HelperContext $helperContext,
        MessageManager $messageManager,
        CustomerRepositoryInterface $customerRepository,
        StoreManagerInterface $storeManager,
        Session $customerSession
    )
    {
        parent::__construct($helperContext);
        $this->messageManager = $messageManager;
        $this->customerRepository =$customerRepository;
        $this->storeManager = $storeManager;
        $this->session = $customerSession;
    }

    /**
     *CMS sync on registration or login or profile edit
     * @param \Gigya\CmsStarterKit\user\GigyaUser $valid_gigya_user
     * @return Customer  $customer
     * @throws \Exception
     */
    public function gigyaSync($valid_gigya_user)
    {
        $customerResult = null; // init result
        $gigyaLoginData = null; // init Gigya Login Data
        $gigyaFilteredData = array();//gigyaFilteredData collection of { gigya_email = gigyaRawData.loginIDs.email, cms_account = null }

        if (!(empty($gigya_uid = $valid_gigya_user->getUID()))) {

            if (!(empty($gigya_loginIDsEmails = $valid_gigya_user->getLoginIDs()['emails']))) {


                foreach ($gigya_loginIDsEmails as $loginIDsEmail) {

                    $ctm =$this->customerRepository->get($loginIDsEmail,$this->storeManager->getWebsite()->getId());
                    //$customerEmail is in $loginIDsEmails

                    if ($loginIDsEmail == $valid_gigya_user->getProfile()->getEmail()) {

                        $gigyaFilteredData[] = [
                            'gigya_email' => $loginIDsEmail,
                            'cms_account' => $ctm,
                            'is_profile_email' => true
                        ];
                    }else{

                        $gigyaFilteredData[] = [
                            'gigya_email' => $loginIDsEmail,
                            'cms_account' => $ctm,
                            'is_profile_email' => false
                        ];
                    }

                }

            } else {
                // $gigya_loginIDsEmails is empty
                $this->messageManager->addError("Email already exists");
            }
        } else {
            //$gigya_uid is empty
            $this->messageManager->addError("UID already exists");
        }

        //  Check if account already exists in CMS
        foreach ($gigyaFilteredData as $row){
            if ($row['cms_account'] != null) {
                // CMS account exists with one of the Gigya emails and same UID
                // CATODO : review loop break condition
                if ($valid_gigya_user->getUID() === $row['cms_account']->getCustomAttribute('gigya_uid')->getValue()) {
                    $gigyaLoginData = $row;

                    //if $gigya_profileEmail is in $gigya_loginIDsEmails it is used as a priority else is ignored
                    if ($row['is_profile_email']){
                        break;
                    }
                }
            }
        }

        //set gigyaRawData on a session object 'gigyaRawData'
        $this->session->setGigyaRawData($valid_gigya_user);

        //set gigyaLoginData.gigya_email on a session object 'gigyaLoggedInEmail'
        if (!(empty($email = $gigyaLoginData['cms_account']->getEmail()))){
            $this->session->setGigyaLoggedInEmail($email);
        }


        if ($gigyaLoginData != null) {

            $customerResult = $gigyaLoginData['cms_account'];

        } else {
            throw new \Exception("Email already exists");
        }

        return $customerResult;
    }
}

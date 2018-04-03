<?php
/**
 * Gigya Controller overriding Magento Customer module Login & Registration controllers. (as defined in etc/di.xml)
 * Add Gigya user validation and account info before continue with Customer flows.
 */
namespace Gigya\GigyaIM\Controller\Raas;

use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Gigya\GigyaIM\Model\Session\Extend;

use Magento\Customer\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\Exception\InputException;
use Magento\Customer\Api\CustomerRepositoryInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GigyaPost extends AbstractLogin
{
	/**
	 * @var GigyaLogger
	 */
	protected $logger;

	/**
	 * @var Extend
	 */
	protected $extendModel;

    /**
     * Create customer account action
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($this->session->isLoggedIn() || !$this->registration->isAllowed()) {
            $resultRedirect->setPath('*/*/');
            return $resultRedirect;
        }

        if (!$this->getRequest()->isPost()) {
            $url = $this->urlModel->getUrl('*/*/create', ['_secure' => true]);
            $resultRedirect->setUrl($this->_redirect->error($url));
            return $resultRedirect;
        }

        $this->session->regenerateId();

        // Gigya logic: validate gigya user -> get Gigya account info -> check if account exists in Magento ->
	    // login /create in magento :

	    $valid_gigya_user = $this->gigyaMageHelper->getGigyaAccountDataFromLoginData($this->getRequest()->getParam('login_data'));
	    $responseObject = $this->doLogin($valid_gigya_user);
	    $response = $this->extractResponseFromDataObject($responseObject);
		$this->applyCookies();

        $this->extendModel->setupSessionCookie();

        $this->applyMessages();

	    /* For correct fixed session sync */
	    try {
		    $sessionExpiration = 0;
		    if (!empty($this->getRequest()->getParam('expires_in'))) {
			    $sessionExpiration = $this->getRequest()->getParam('expires_in');
		    }
		    $this->extendModel->extendSession(false, $sessionExpiration);
	    } catch (\Exception $e) {
		    return false;
	    }

	    if (!empty($this->getRequest()->getParam('login_event'))) {
		    echo $responseObject->toJson();
		    return null;
	    }

	    return $response;
    }

    /**
     * Make sure that password and password confirmation matched
     *
     * @param string $password
     * @param string $confirmation
     * @return void
     * @throws InputException
     */
    protected function checkPasswordConfirmation($password, $confirmation)
    {
        if ($password != $confirmation) {
            throw new InputException(__('Please make sure your passwords match.'));
        }
    }
}

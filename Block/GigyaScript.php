<?php
/*
 * Manage adding Gigya script with API key and global variables
 * Defined in view/frontend/layout/default.xml
 */
namespace Gigya\GigyaM2\Block;
use Magento\Framework\View\Element\Template;

class GigyaScript extends Template
{
    /**
     * @var int
     */
    private $_username = -1;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Customer\Model\Url
     */
    protected $_customerUrl;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Customer\Model\Url $customerUrl
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Model\Url $customerUrl,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_isScopePrivate = false;
        $this->_customerUrl = $customerUrl;
        $this->_customerSession = $customerSession;
    }

    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    /**
     * @return String Gigya API key set in default.xml
     */
    public function getGigyaApiKey() {
        return $this->getData('apikey');
    }

    public function getBaseUrl()
    {
        return $this->getUrl('/', ['_secure' => $this->getRequest()->isSecure()]);
    }

    /**
     * Retrieve form posting url
     *
     * @return string
     */
    public function getPostActionUrl()
    {
        return $this->_customerUrl->getLoginPostUrl();
    }

    /**
     * Check if user is logged in
     * @return int
     */
    public function getMagentoUserLogin() {
        $logged_in = $this->_customerSession->isLoggedIn();
        if ($logged_in) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * @return int
     */
    public function getGigyaUserLogin() {
        $search = "/glt/";
        $result = preg_grep("[glt]", array_flip($_COOKIE));
        if (sizeof($result) > 0) {
            return 1;
        } else {
            return 0;
        }
    }

}
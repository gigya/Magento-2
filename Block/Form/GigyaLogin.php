<?php
/**
 * Gigya override of standard login block of Magento
 */

namespace Gigya\GigyaIM\Block\Form;

use \Magento\Customer\Block\Form\Login;
use \Gigya\GigyaIM\Model\Config;
use \Magento\Framework\View\Element\Template\Context;
use \Magento\Customer\Model\Session;
use \Magento\Customer\Model\Url;

/**
 * Class Login
 *
 * @package Gigya\GigyaIM\Block\Form
 */
class GigyaLogin extends Login
{
    /**
     * @var Config
     */
    protected $configModel;

    /**
     * Login constructor.
     *
     * @param Context $context
     * @param Session $customerSession
     * @param Url     $customerUrl
     * @param Config  $configModel
     * @param array   $data
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        Url $customerUrl,
        Config $configModel,
        array $data = []
    ) {
        $this->configModel = $configModel;
        parent::__construct($context, $customerSession, $customerUrl, $data);
    }

    /**
     * @return string
     */
    public function getLoginDesktopScreensetId()
    {
        return $this->configModel->getLoginDesktopScreensetId();
    }

    /**
     * @return string
     */
    public function getLoginMobileScreensetId()
    {
        return $this->configModel->getLoginMobileScreensetId();
    }

    public function getRedirectUrl()
    {
        return $this->_customerUrl->getLoginPostUrl();
    }
}
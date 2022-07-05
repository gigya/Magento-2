<?php
/**
 * Gigya override of standard login block of Magento
 */

namespace Gigya\GigyaIM\Block\Form;

use \Magento\Customer\Block\Account\AuthenticationPopup;
use \Magento\Framework\View\Element\Template\Context;
use \Magento\Framework\Serialize\Serializer\Json;
use \Gigya\GigyaIM\Model\Config;

/**
 * Class Login
 *
 * @package Gigya\GigyaIM\Block\Form
 */
class GigyaAuthenticationPopup extends AuthenticationPopup
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * GigyaAuthenticationPopup constructor.
     * @param Context $context
     * @param array $data
     * @param Json|null $serializer
     * @param Config $config
     */
    public function __construct(
        Context $context,
        Config $config,
        array $data = [],
        Json $serializer = null
    ) {
        $this->config = $config;

        parent::__construct($context, $data, $serializer);
    }

    /**
     * @return string
     */
    public function getLoginDesktopScreensetId()
    {
        return $this->config->getLoginDesktopScreensetId();
    }

    /**
     * @return string
     */
    public function getLoginMobileScreensetId()
    {
        return $this->config->getLoginMobileScreensetId();
    }

    /**
     * @return string
     */
    public function _toHtml() {

        if ($this->config->isGigyaEnabled()) {
            $this->getLayout()->unsetElement('customer_form_register');
            $this->getLayout()->unsetElement('customer_edit');
        }

        return parent::_toHtml();
    }
}
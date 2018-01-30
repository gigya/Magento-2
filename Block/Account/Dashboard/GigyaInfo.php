<?php
/**
 * Gigya override of standard customer dashboard
 */

namespace Gigya\GigyaIM\Block\Account\Dashboard;

use \Magento\Customer\Block\Account\Dashboard\Info;
use \Gigya\GigyaIM\Model\Config;
use \Magento\Framework\View\Element\Template\Context;
use \Magento\Customer\Helper\Session\CurrentCustomer;
use \Magento\Newsletter\Model\SubscriberFactory;
use \Magento\Customer\Helper\View;

/**
 * Class Info
 *
 * @package Gigya\GigyaIM\Block\Account\Dashboard
 */
class GigyaInfo extends Info
{
    /**
     * @var Config
     */
    protected $configModel;

    /**
     * Info constructor.
     *
     * @param Context           $context
     * @param CurrentCustomer   $currentCustomer
     * @param SubscriberFactory $subscriberFactory
     * @param View              $helperView
     * @param Config            $configModel
     * @param array             $data
     */
    public function __construct(
        Context $context,
        CurrentCustomer $currentCustomer,
        SubscriberFactory $subscriberFactory,
        View $helperView,
        Config $configModel,
        array $data = []
    ) {
        $this->configModel = $configModel;
        parent::__construct($context, $currentCustomer, $subscriberFactory, $helperView, $data);
    }

    /**
     * @return string
     */
    public function getProfileDesktopScreensetId()
    {
        return $this->configModel->getProfileDesktopScreensetId();
    }

    /**
     * @return string
     */
    public function getProfileMobileScreensetId()
    {
        return $this->configModel->getProfileMobileScreensetId();
    }
}
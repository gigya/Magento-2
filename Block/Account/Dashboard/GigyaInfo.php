<?php

namespace Gigya\GigyaIM\Block\Account\Dashboard;

/**
 * Class GigyaInfo
 * @package Gigya\GigyaIM\Block\Account\Dashboard
 */
class GigyaInfo extends \Magento\Customer\Block\Account\Dashboard\Info
{
    /**
     * @var \Gigya\GigyaIM\Model\Config
     */
    protected $configModel;

    /**
     * GigyaInfo constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Helper\Session\CurrentCustomer $currentCustomer
     * @param \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory
     * @param \Magento\Customer\Helper\View $helperView
     * @param \Gigya\GigyaIM\Model\Config $configModel
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Helper\Session\CurrentCustomer $currentCustomer,
        \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory,
        \Magento\Customer\Helper\View $helperView,
        \Gigya\GigyaIM\Model\Config $configModel,
        array $data = []
    ) {
        $this->configModel = $configModel;
        parent::__construct($context, $currentCustomer, $subscriberFactory, $helperView, $data);
    }

    /**
     * @return string
     */
    public function getScreensetName()
    {
        return $this->configModel->getScreensetName();
    }
}

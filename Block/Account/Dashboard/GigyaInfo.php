<?php

namespace Gigya\GigyaIM\Block\Account\Dashboard;

class GigyaInfo extends \Magento\Customer\Block\Account\Dashboard\Info
{
    /**
     * @var \Gigya\GigyaIM\Model\Config
     */
    protected $configModel;

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

    public function getScreensetName()
    {
        return $this->configModel->getScreensetName();
    }
}
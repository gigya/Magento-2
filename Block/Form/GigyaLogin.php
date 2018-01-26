<?php

namespace Gigya\GigyaIM\Block\Form;

class GigyaLogin extends \Magento\Customer\Block\Form\Login
{
    /**
     * @var \Gigya\GigyaIM\Model\Config
     */
    protected $configModel;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Model\Url $customerUrl,
        \Gigya\GigyaIM\Model\Config $configModel,
        array $data = []
    ) {
        $this->configModel = $configModel;
        parent::__construct($context, $customerSession, $customerUrl, $data);
    }

    public function getScreensetName()
    {
        return $this->configModel->getScreensetName();
    }
}
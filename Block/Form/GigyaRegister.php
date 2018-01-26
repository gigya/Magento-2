<?php

namespace Gigya\GigyaIM\Block\Form;

class GigyaRegister extends \Magento\Customer\Block\Form\Register
{
    /**
     * @var \Gigya\GigyaIM\Model\Config
     */
    protected $configModel;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Directory\Helper\Data $directoryHelper,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\Framework\App\Cache\Type\Config $configCacheType,
        \Magento\Directory\Model\ResourceModel\Region\CollectionFactory $regionCollectionFactory,
        \Magento\Directory\Model\ResourceModel\Country\CollectionFactory $countryCollectionFactory,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Model\Url $customerUrl,
        \Gigya\GigyaIM\Model\Config $configModel,
        array $data = []
    ) {
        $this->configModel = $configModel;
        parent::__construct($context, $directoryHelper, $jsonEncoder, $configCacheType, $regionCollectionFactory,
            $countryCollectionFactory, $moduleManager, $customerSession, $customerUrl, $data);
    }

    public function getScreensetName()
    {
        return $this->configModel->getScreensetName();
    }
}

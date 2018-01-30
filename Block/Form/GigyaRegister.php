<?php
/**
 * Gigya override of standard register block of Magento
 */

namespace Gigya\GigyaIM\Block\Form;

use \Magento\Customer\Block\Form\Register;
use \Gigya\GigyaIM\Model\Config as GigyaConfig;
use \Magento\Framework\View\Element\Template\Context;
use \Magento\Directory\Helper\Data;
use \Magento\Framework\Json\EncoderInterface;
use \Magento\Framework\App\Cache\Type\Config;
use \Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use \Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use \Magento\Framework\Module\Manager;
use \Magento\Customer\Model\Session;
use \Magento\Customer\Model\Url;

/**
 * Class Register
 *
 * @package Gigya\GigyaIM\Block\Form
 */
class GigyaRegister extends Register
{
    /**
     * @var GigyaConfig
     */
    protected $configModel;

    /**
     * Register constructor.
     *
     * @param Context                  $context
     * @param Data                     $directoryHelper
     * @param EncoderInterface         $jsonEncoder
     * @param Config                   $configCacheType
     * @param RegionCollectionFactory  $regionCollectionFactory
     * @param CountryCollectionFactory $countryCollectionFactory
     * @param Manager                  $moduleManager
     * @param Session                  $customerSession
     * @param Url                      $customerUrl
     * @param GigyaConfig              $configModel
     * @param array                    $data
     */
    public function __construct(
        Context $context,
        Data $directoryHelper,
        EncoderInterface $jsonEncoder,
        Config $configCacheType,
        RegionCollectionFactory $regionCollectionFactory,
        CountryCollectionFactory $countryCollectionFactory,
        Manager $moduleManager,
        Session $customerSession,
        Url $customerUrl,
        GigyaConfig $configModel,
        array $data = []
    ) {
        $this->configModel = $configModel;
        parent::__construct(
            $context, $directoryHelper, $jsonEncoder, $configCacheType, $regionCollectionFactory,
            $countryCollectionFactory, $moduleManager, $customerSession, $customerUrl, $data
        );
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
}
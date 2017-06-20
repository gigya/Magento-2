<?php
/**
 * Validate that admin API settings are correct.
 * on Gigya admin page save, take the submitted API, DC, Aapp key, and apps secret and create Gigya REST request
 */
namespace Gigya\GigyaIM\Model\Config;

/**
 * Customer sharing config model
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class ValidateApikey extends \Magento\Framework\App\Config\Value
{

    /**
     * @var \Magento\Customer\Model\ResourceModel\Customer
     */
    protected $_customerResource;

    /** @var  \Magento\Store\Model\StoreManagerInterface */
    protected $_storeManager;

    /** @var  \Gigya\GigyaIM\Helper\GigyaMageHelper */
    protected $gigyaMageHelper;

    /**
     * Constructor
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Customer\Model\ResourceModel\Customer $customerResource
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\ResourceModel\Customer $customerResource,
        \Gigya\GigyaIM\Helper\GigyaMageHelper $gigyaMageHelper,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_storeManager = $storeManager;
        $this->_customerResource = $customerResource;
        $this->gigyaMageHelper = $gigyaMageHelper;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Check for email duplicates before saving customers sharing options
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function beforeSave()
    {
        // get submitted settings
        $api_key = $this->_data['fieldset_data']['api_key'];
        $domain = $this->_data['fieldset_data']['domain'];
        $app_key = $this->_data['fieldset_data']['app_key'];
        $key_file_location = $this->_data['fieldset_data']['key_file_location'];
        // *** cancel key save type option in admin

        // create object manager and reset the settings to newly submitted
        $this->gigyaMageHelper->setApiKey($api_key);
        $this->gigyaMageHelper->setApiDomain($domain);
        $this->gigyaMageHelper->setAppKey($app_key);
        $this->gigyaMageHelper->setKeyFileLocation($key_file_location);
        $this->gigyaMageHelper->setAppSecret();
        $gigyaApiHelper = $this->gigyaMageHelper->getGigyaApiHelper();

        //make the call to gigya REST API
        $param = array("filter" => 'full');
        try {
            $gigyaApiHelper->sendApiCall("accounts.getSchema", $param);
        } catch (\Gigya\CmsStarterKit\sdk\GSApiException $e) {
            $this->gigyaMageHelper->gigyaLog(
                "Error while trying to save gigya settings. " . $e->getErrorCode() .
                " " .$e->getMessage() . " " . $e->getCallId()
            );
            //@codingStandardsIgnoreStart
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    "Could not save settings. Gigya API test failed with error message: {$e->getMessage()} ."
                )
            );
        }

        return $this;
    }

}

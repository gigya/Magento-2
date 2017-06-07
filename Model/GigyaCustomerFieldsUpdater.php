<?php

namespace Gigya\GigyaIM\Model;

use Gigya\CmsStarterKit\fieldMapping;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Helper\GigyaSyncHelper;
use Gigya\GigyaIM\Model\Cache\Type\FieldMapping as CacheType;
use Magento\Customer\Model\Data\Customer;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * GigyaCustomerFieldsUpdater
 *
 * Map Magento Customer's entity data to Gigya data.
 *
 * The event 'pre_sync_to_gigya' is triggered before performing the mapping based on the field mapping .json configuration file
 * pointed to by self::path - use self:setPath() to set it.
 *
 * The event will hang a data 'customer' of type Magento\Customer\Model\Customer
 * Third party code can catch this event to perform any necessary operation on the Magento's customer data before the mapping is done.
 *
 * @author      vlemaire <info@x2i.fr>
 *
 */
class GigyaCustomerFieldsUpdater extends fieldMapping\GigyaUpdater
{

    /** @var CacheType CacheType */
    protected $gigyaCacheType;

    /** @var  EventManagerInterface */
    protected $eventManager;

    /** @var GigyaLogger */
    protected $logger;

    /** @var  Customer */
    private $magentoCustomer;

    /** @var  GigyaUser */
    private $gigyaUser;

    /** @var fieldMapping\Conf|bool  */
    private $confMapping = false;

    /** @var GigyaSyncHelper */
    private $gigyaSyncHelper;

    /** @var array */
    private $magentoCustomerArray;

    /**
     * GigyaCustomerFieldsUpdater constructor.
     *
     * @param GigyaMageHelper $gigyaMageHelper
     * @param CacheType $gigyaCacheType
     * @param EventManagerInterface $eventManager
     * @param GigyaLogger $logger
     */
    public function __construct(
        GigyaMageHelper $gigyaMageHelper,
        CacheType $gigyaCacheType,
        EventManagerInterface $eventManager,
        GigyaLogger $logger,
        GigyaSyncHelper $gigyaSyncHelper
    )
    {
        $apiHelper = $gigyaMageHelper->getGigyaApiHelper();

        parent::__construct(null, null, null, $apiHelper);

        $this->gigyaCacheType = $gigyaCacheType;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
        $this->gigyaSyncHelper = $gigyaSyncHelper;
        $this->magentoCustomerArray = [];
    }

    /**
     * @inheritdoc
     *
     * Magento customer to use for mapping must be set before this method by calling self::getMagentoCustomer()
     *
     */
    public function callCmsHook() {
        $customerData = new DataObject(['customer_data' => $this->magentoCustomerArray]);
        $this->eventManager->dispatch(
            "pre_sync_to_gigya",
            [
                "data_object" => $customerData
            ]
        );
        $this->magentoCustomerArray = $customerData->getData('customer_data');

        $cmsArray = [];

        $cmsKeyed = $this->getMappingFromCache()->getCmsKeyed();
        foreach ($cmsKeyed as $cmsName => $confs) {
            $value = $this->getValueFromMagentoCustomer($cmsName);
            $cmsArray[$cmsName] = $value;
        }

        $this->setCmsArray($cmsArray);
    }

    /**
     * @inheritdoc
     */
    public function updateGigya()
    {
        parent::updateGigya();

        $updatedGigyaData = $this->getGigyaArray();
        if (array_key_exists('profile', $updatedGigyaData)) {
            $updatedGigyaProfile = $updatedGigyaData['profile'];
            foreach ($updatedGigyaProfile as $name => $value) {
                $methodName = 'set' . ucfirst($name);
                $methodParams = $value;
                call_user_func(array($this->gigyaUser->getProfile(), $methodName), $methodParams);
            }
        }
    }

    /**
     * @return Customer
     */
    public function getMagentoCustomer()
    {
        return $this->magentoCustomer;
    }

    /**
     * @param Customer $magentoCustomer
     */
    public function setMagentoCustomer($magentoCustomer)
    {
        $this->magentoCustomer = $magentoCustomer;
        $this->magentoCustomerArray = $this->gigyaSyncHelper->getCustomerData($magentoCustomer);
    }

    public function setGigyaUser($gigyaUser)
    {

        $this->gigyaUser = $gigyaUser;
    }

    public function getGigyaUser()
    {
        return $this->gigyaUser;
    }

    /**
     * Facility for converting snake case to camel case (with 1st letter uppercase)
     *
     * @param $word
     * @param $delimiter
     * @return mixed
     */
    private function mixify($word, $delimiter){

        $word = strtolower($word);
        $word = ucwords($word, $delimiter);

        return str_replace($delimiter, '', $word);
    }

    /**
     * Retrieve a Customer entity attribute value.
     *
     * cf. $cmsName doc for syntax.
     * No check is made on the existence of intermediate attributes, if any : if an intermediate attribute is null or not found an exception will be thrown.
     *
     * @param $cmsName string
     *      A 'path' to the value to retrieve on the Customer entity. Syntax is : word[.word]+ where word is the snake case name of a property (eg 'my_attribute')
     *      If word begins with custom_, like in custom_my_attribute, we'll call magentoCustomer->getCustomAttriubte('my_attribute')
     *      Otherwise we'll call magentoCustomer->getMyAttribute
     * @return mixed
     * @throws GigyaFieldMappingException
     */
    public function getValueFromMagentoCustomer($cmsName)
    {
        $subPaths = explode('.', $cmsName);
        if (empty($subPaths)) {
            $this->logger->warning(sprintf("cmsName should not be empty."));
            return null;
        }

        $value = null;

        while (($subPath = array_shift($subPaths)) != null) {
            if(isset($this->magentoCustomerArray[$subPath]))
            {
                $value = $this->magentoCustomerArray[$subPath];
            }
            else
            {
                throw new GigyaFieldMappingException(
                    sprintf(
                        'Field mapping Magento to Gigya : exception while looking for Customer entity value [%s]',
                        $cmsName
                    )
                );
            }
        }

        return $value;
    }

    /**
     * @inheritdoc
     *
     * Magento customer to use for mapping must be set before this method by calling self::getMagentoCustomer()
     *
     * @throws GigyaFieldMappingException If Magento customer is not set.
     */
    protected function retrieveFieldMappings()
    {
        if ($this->magentoCustomer == null) {
            throw new GigyaFieldMappingException("Magento customer is not set");
        }

        parent::retrieveFieldMappings();
    }

    /**
     * @inheritdoc
     *
     * Magento customer to use for mapping must be set before this method by calling self::getMagentoCustomer()
     * Once parsed here, the attribute magentoCustomer is set to null.
     *
     * @throws GigyaFieldMappingException If Magento customer is not set.
     */
    protected function createGigyaArray()
    {
        if ($this->magentoCustomer == null) {
            throw new GigyaFieldMappingException("Magento customer is not set");
        }

        $result = null;

        try {
            $result = parent::createGigyaArray();
        } finally {
            $this->magentoCustomer = null;
        }

        return $result;
    }

    /**
     * Nothing done here. This method exists for interface compatibility with php_cms_kit (aka cms-starter-kit) module
     *
     * Reasons are : retry on Gigya update + gigyaUid can be set by parent class only on constructor (M2 good practice is to use a singleton)
     */
    protected function callSetAccountInfo()
    {
         $this->getGigyaArray();
    }

    /**
     * @inheritdoc
     *
     * If the cache is deactivated we put the data on the attribute self::confMapping
     *
     * @param fieldMapping\Conf $mappingConf
     */
    protected function setMappingCache($mappingConf)
    {
        if (!$this->gigyaCacheType->test(CacheType::CACHE_TAG)) {
            $this->confMapping = $mappingConf;
        } else {
            $this->gigyaCacheType->save(serialize($mappingConf), CacheType::TYPE_IDENTIFIER, [CacheType::CACHE_TAG],
                86400);
        }
    }

    /**
     * @inheritdoc
     *
     * @return fieldMapping\Conf|false False if the cache is deactivated and the method self::setMappingCache() has not been called yet on this instance.
     */
    protected function getMappingFromCache()
    {
        if (!$this->gigyaCacheType->test(CacheType::CACHE_TAG)) {
            return $this->confMapping;
        }

        return unserialize($this->gigyaCacheType->load(CacheType::TYPE_IDENTIFIER));
    }
}
<?php

namespace Gigya\GigyaIM\Model;

use Gigya\CmsStarterKit\fieldMapping;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Model\Cache\Type\FieldMapping as CacheType;
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
class GigyaCustomerFieldsUpdater extends AbstractGigyaFieldsUpdater
{

    /** @var CacheType CacheType */
    protected $gigyaCacheType;

    /** @var  EventManagerInterface */
    protected $eventManager;

    /** @var GigyaLogger */
    protected $logger;

    /** @var  GigyaUser */
    private $gigyaUser;

    /** @var fieldMapping\Conf|bool  */
    private $confMapping = false;

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
        GigyaLogger $logger
    )
    {
        $apiHelper = $gigyaMageHelper->getGigyaApiHelper();

        parent::__construct(null, null, null, $apiHelper);

        $this->gigyaCacheType = $gigyaCacheType;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     *
     * Magento customer to use for mapping must be set before this method by calling self::getMagentoCustomer()
     *
     */
    public function callCmsHook() {
        $this->eventManager->dispatch(
            "pre_sync_to_gigya",
            [
                "customer" => $this->getMagentoUser(),
                "gigya_user" => $this->getGigyaUser()
            ]
        );

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
        if (array_key_exists('data', $updatedGigyaData)) {
            $this->gigyaUser->setData($updatedGigyaData['data']);
        }
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

        $value = $this->getMagentoUser();
        try {
            while (($subPath = array_shift($subPaths)) != null) {

                if (strpos($subPath, 'custom_') === 0) {
                    $subPath = substr($subPath, 7);
                    $methodName = 'getCustomAttribute';
                    $methodParams = strtolower($subPath);
                    $value = call_user_func(array($value, $methodName), $methodParams);
                    if ($value == null) {
                        throw new \Exception('Custom attribute '.$subPath.' is not set');
                    }
                    /* value is of type AttributeValue */
                    $value = $value->getValue();
                } else {
                    $methodName = 'get' . ucfirst($this->mixify($subPath, '_'));
                    $value = call_user_func(array($value, $methodName));
                }
            }
        } catch(\Exception $e) {
            throw new GigyaFieldMappingException(sprintf('Field mapping Magento to Gigya : exception while looking for Customer entity value [%s] : %s', $cmsName, $e->getMessage()));
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
        if ($this->getMagentoUser() == null) {
            throw new GigyaFieldMappingException("Magento customer is not set");
        }

        try {
            parent::retrieveFieldMappings();
        } catch(\Exception $e) {
            if (!$this->confMapping || $this->confMapping->getMappingConf() == null) {
                throw new GigyaFieldMappingException("Field mapping file could not be found or is empty or is not correctly formated.");
            } else {
                throw $e;
            }
        }
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
        if ($this->getMagentoUser() == null) {
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
<?php

namespace Gigya\GigyaIM\Model;

use Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaSubscription;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Helper\GigyaMageHelper;
use Gigya\GigyaIM\Model\Cache\Type\FieldMapping as CacheType;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Newsletter\Model\Subscriber;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Customer\Model\ResourceModel\Address as AddressResourceModel;
use Magento\Customer\Model\AddressFactory;

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
 */
class GigyaCustomerFieldsUpdater extends AbstractGigyaFieldsUpdater
{
    /** @var CacheType CacheType */
    protected $gigyaCacheType;

    /** @var  EventManagerInterface */
    protected $eventManager;

    /** @var GigyaLogger */
    protected $logger;

    protected $magentoCustomer;

    /** @var  GigyaUser */
    private $gigyaUser;

    /** @var Subscriber */
    private $subscriber;

    /** @var fieldMapping\Conf|bool  */
    protected $confMapping = false;

    /** @var AddressResourceModel */
    public $addressResourceModel;

    /** @var AddressFactory */
    public $addressFactory;

    /**
     * GigyaCustomerFieldsUpdater constructor.
     *
     * @param GigyaMageHelper $gigyaMageHelper
     * @param CacheType $gigyaCacheType
     * @param EventManagerInterface $eventManager
     * @param GigyaLogger $logger
     * @param Subscriber $subscriber
     * @param AddressResourceModel $addressResourceModel
     * @param AddressFactory $addressFactory
     */
    public function __construct(
        GigyaMageHelper $gigyaMageHelper,
        CacheType $gigyaCacheType,
        EventManagerInterface $eventManager,
        GigyaLogger $logger,
        Subscriber $subscriber,
        AddressResourceModel $addressResourceModel,
        AddressFactory $addressFactory
    )
    {
        $apiHelper = $gigyaMageHelper->getGigyaApiHelper();

		parent::__construct(null, null, null, $apiHelper);

        $this->gigyaCacheType = $gigyaCacheType;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
        $this->subscriber = $subscriber;
        $this->addressResourceModel = $addressResourceModel;
        $this->addressFactory = $addressFactory;
    }

    /**
     * @inheritdoc
     *
     * Magento customer to use for mapping must be set before this method by calling self::getMagentoCustomer()
     *
	 * @throws GigyaFieldMappingException
     */
    public function callCmsHook()
    {
        $this->eventManager->dispatch(
            'pre_sync_to_gigya',
            ['customer' => $this->getMagentoUser(), 'gigya_user' => $this->getGigyaUser()]
        );

        $cmsArray = [];

        $cmsKeyed = $this->getMappingFromCache()->getCmsKeyed();
        foreach ($cmsKeyed as $cmsName => $confs) {
            $value = $this->getValueFromMagentoCustomer($cmsName);
            $cmsArray[$cmsName] = $value;
        }

        $this->setGigyaUid($this->gigyaUser->getUID());
        $this->setCmsArray($cmsArray);
    }

    /**
     * @inheritdoc
     */
    public function updateGigya()
    {
		parent::updateGigya(); /* In order to allow saving a Magento 2 customer even when there is an error from Gigya, surround this with a try/catch */

        /** @var array $updatedGigyaData */
        $updatedGigyaData = $this->getGigyaArray();

        /** @var array $array */
        foreach ($updatedGigyaData as $key => $array) {
            if ($key === 'profile') {
                $updatedGigyaProfile = $array;
                foreach ($updatedGigyaProfile as $name => $value) {
                    $methodName = 'set' . ucfirst($name);
                    $methodParams = $value;
                    call_user_func(array($this->gigyaUser->getProfile(), $methodName), $methodParams);
                }
            } elseif ($key === 'data') {
                $this->gigyaUser->setData($updatedGigyaData['data']);
            } elseif ($key === 'subscriptions') {
                /* Specific code for subscriptions */
                /** @var array $subscriptionData */
                foreach ($array as $subscriptionId => $subscriptionData) {
                	if (array_key_exists('email', $subscriptionData) and is_array($subscriptionData['email'])) {
                        $subscription = new GigyaSubscription(null);

                        foreach ($subscriptionData['email'] as $subscriptionField => $subscriptionValue) {
                            $methodName = 'set' . ucfirst($subscriptionField);
                            $methodParams = $subscriptionValue;
                            $subscription->$methodName($methodParams);
                        }
                        $this->gigyaUser->addSubscription($subscriptionId, $subscription);
                    }
                }
            } else {
                /* Specific code for other fields */
                $methodName = 'set' . ucfirst($key);
                $methodParams = $array;
                call_user_func(array($this->gigyaUser, $methodName), $methodParams);
            }
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
     *      If word begins with custom_, like in custom_my_attribute, we'll call magentoCustomer->getCustomAttribute('my_attribute')
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

        $magentoUser = $this->getMagentoUser();
        try {
            while (($subPath = array_shift($subPaths)) != null) {
                if (strpos($subPath, 'custom_') === 0) {
                    $subPath = substr($subPath, 7);
                    $methodName = 'getCustomAttribute';
                    $methodParams = strtolower($subPath);
                    $value = call_user_func(array($magentoUser, $methodName), $methodParams);
                    if ($value == null) {
                        throw new \Exception('Custom attribute '.$subPath.' is not set');
                    }

                    /* Value is of type AttributeValue */
                    $value = $value->getValue();
                }
                elseif ($subPath === 'isSubscribed')
                {
                    $subscriber = $this->subscriber->loadByCustomerId($magentoUser->getId());
                    $value = $subscriber->isSubscribed();
                } elseif (strpos($subPath, 'address_') === 0) {
                    $subPath = substr($subPath, 8);
                    $param0 = null;

                    /** @var \Magento\Customer\Model\Address $magentoBilling */
                    $magentoBilling = $this->addressFactory->create();
                    $this->addressResourceModel->load($magentoBilling, $magentoUser->getDefaultBilling());
                    $methodName = 'get' . ucfirst($subPath);

                    if (in_array($subPath, ['street0', 'street1', 'street2', 'street3'])) {
                        $param0 = substr($subPath, -1);
                        $methodName = 'getStreetLine';
                    }

                    $value = call_user_func(array($magentoBilling, $methodName), $param0);
                    //If the fieldset is set to street, then implode address lines
                    $value = is_array($value) ? implode(', ', $value) : $value;
                } else {
                    $methodName = 'get' . ucfirst($this->mixify($subPath, '_'));
                    $value = call_user_func(array($magentoUser, $methodName)) ?: '';
                }
            }
        } catch(\Exception $e) {
            throw new GigyaFieldMappingException(sprintf('Field mapping Magento to Gigya : exception while looking for Customer entity value [%s] : %s', $cmsName, $e->getMessage()));
        }

        return (isset($value)) ? $value : $magentoUser;
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
    public function setMappingCache($mappingConf)
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
    public function getMappingFromCache()
    {
        if (!$this->gigyaCacheType->test(CacheType::CACHE_TAG)) {
            return $this->confMapping;
        }

        return unserialize($this->gigyaCacheType->load(CacheType::TYPE_IDENTIFIER));
    }
}
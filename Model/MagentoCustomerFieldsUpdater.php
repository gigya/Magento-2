<?php

namespace Gigya\GigyaIM\Model;

use Gigya\CmsStarterKit\fieldMapping;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Model\Cache\Type\FieldMapping as CacheType;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * MagentoCustomerFieldsUpdater
 *
 * update customer fields with mapped fields from Gigya.
 * See Magento prepared methods at: app/code/Magento/Customer/Model/Data/Customer.php
 * helpful magento guide for creating custom fields:
 * https://maxyek.wordpress.com/2015/10/22/building-magento-2-extension-customergrid/comment-page-1/
 *
 * For mapping existing Magento custom fields to gigya fields:
 * use: $customer->setCustomAttribute($attributeCode, $attributeValue);
 * or: $customer->setCustomAttributes(array());
 * located at: /lib/internal/Magento/Framework/Api/AbstractExtensibleObject
 */
class MagentoCustomerFieldsUpdater extends AbstractMagentoFieldsUpdater
{
    /** @var CacheType CacheType */
    protected $gigyaCacheType;

    /** @var  EventManagerInterface */
    protected $eventManager;

    /** @var GigyaLogger  */
    public $logger;

    /** @var fieldMapping\Conf|bool  */
    protected $confMapping = false;

    /**
     * MagentoCustomerFieldsUpdater constructor.
     *
     * @param CacheType $gigyaCacheType
     * @param EventManagerInterface $eventManager
     * @param GigyaLogger $logger
     */
    public function __construct(
        CacheType $gigyaCacheType,
        EventManagerInterface $eventManager,
        GigyaLogger $logger
    )
    {
        parent::__construct(new GigyaUser(null), null);

        $this->gigyaCacheType = $gigyaCacheType;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
    }

    public function callCmsHook() {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\Event\ManagerInterface $manager */
        $manager = $om->get('Magento\Framework\Event\ManagerInterface');
        $params = array(
            "gigya_user" => $this->getGigyaUser(),
            "customer" => $this->getMagentoUser()

        );
        $manager->dispatch("gigya_pre_field_mapping", $params);
    }

    public function setGigyaLogger($logger) {
        $this->_logger = $logger;
    }

    /**
     * @param Magento/Customer $account
     */
    public function setAccountValues(&$account) {
        foreach ($this->getGigyaMapping() as $gigyaName => $confs) {
            /** @var \Gigya\CmsStarterKit\fieldMapping\ConfItem $conf */
            $value = parent::getValueFromGigyaAccount($gigyaName); // e.g: loginProvider = facebook
            // if no value found, log and skip field
            if (is_null($value)) {
                $this->logger->info( __FUNCTION__ . ": Value for {$gigyaName} not found in gigya user object. check your field mapping configuration");
                continue;
            }
            foreach ($confs as $conf) {
                $mageKey = $conf->getCmsName();     // e.g: mageKey = prefix
                $value   = $this->castValue($value, $conf);

                if (gettype($value) == "boolean") {
                    $value = $this->transformGigyaToMagentoBoolean($value);
                }

                if (substr($mageKey, 0, 6) === "custom") {
                    $key = substr($mageKey, 7);

                    if($account instanceof AbstractExtensibleModel)
                    {
                        $account->setCustomAttribute($key, $value);
                    }
                    else
                    {
                        $account->setData($key, $value);
                    }
                } else {
                    $account->setData($mageKey, $value);
                }
            }
        }
    }

    /**
     * Transform Gigya boolean to Magento boolean - '0'/'1' values
     * @param bool $gigya_bool
     * @return string $magento_bool
     */
    protected function transformGigyaToMagentoBoolean($gigya_bool) {
        if ($gigya_bool == true) {
            $magento_bool = '1';
        } else {
            $magento_bool = '0';
        }
        return $magento_bool;
    }

    /**
     * Nothing done here. This method exists for interface compatibility with php_cms_kit (aka cms-starter-kit) module
     *
     * Save will be performed by CATODO
     *
     * Reasons is : retry on M2 update
     */
    public function saveCmsAccount(&$cmsAccount, $cmsAccountSaver = null)
    {
    }

    public function retrieveFieldMappings()
    {
        $conf = $this->getMappingFromCache();
        if (false === $conf) {
            $mappingJson = file_get_contents($this->getPath());
            if (false === $mappingJson) {
                $err     = error_get_last();
                $message = "Could not retrieve field mapping configuration file. message was:" . $err['message'];
                throw new \Exception("$message");
            }
            $conf = new fieldMapping\Conf($mappingJson);
            $this->setMappingCache($conf);
        }
        $this->setGigyaMapping($conf->getGigyaKeyed());
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
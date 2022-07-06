<?php

namespace Gigya\GigyaIM\Model;

use Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Gigya\GigyaIM\Model\Cache\Type\FieldMapping as CacheType;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory as AddressFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\ObjectManagerInterface;

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

    /** @var AddressFactory */
    protected $addressFactory;

    /** @var AddressRepositoryInterface */
    protected $addressRepository;

    /**
     * MagentoCustomerFieldsUpdater constructor.
     *
     * @param CacheType $gigyaCacheType
     * @param EventManagerInterface $eventManager
     * @param GigyaLogger $logger
     * @param AddressFactory $addressFactory
     * @param AddressRepositoryInterface $addressRepository
     */
    public function __construct(
        CacheType $gigyaCacheType,
        EventManagerInterface $eventManager,
        GigyaLogger $logger,
        AddressFactory $addressFactory,
        AddressRepositoryInterface $addressRepository
    ) {
        parent::__construct(new GigyaUser(null), null);

        $this->gigyaCacheType = $gigyaCacheType;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
        $this->addressFactory = $addressFactory;
        $this->addressRepository = $addressRepository;
    }

    /**
     * Method callCmsHook
     */
    public function callCmsHook()
    {
        /** @var ObjectManagerInterface $om */
        $om = ObjectManager::getInstance();
        /** @var ManagerInterface $manager */
        $manager = $om->get('Magento\Framework\Event\ManagerInterface');

        $params = [
            'gigya_user' => $this->getGigyaUser(),
            'customer' => $this->getMagentoUser()
        ];

        $manager->dispatch('gigya_pre_field_mapping', $params);
    }

    /**
     * @param \Magento\Customer\Model\Data\Customer $account
     */
    public function setAccountValues(&$account)
    {
        $gigyaMapping = $this->getGigyaMapping();
        $magentoBillingAddressId = $account->getDefaultBilling();
        try {
            $magentoBillingAddress = $this->addressRepository->getById($magentoBillingAddressId);
        } catch (\Exception $ex) {
            $this->logger->error($ex->__toString());
            $magentoBillingAddress = false;
        }

        if (null === $magentoBillingAddress) {
            $magentoBillingAddress = false;
        }

        if ($magentoBillingAddress === false) {
            $isBillingAddressNew = true;
            /** @var \Magento\Customer\Model\Data\Address $magentoBillingAddress */
            $magentoBillingAddress = $this->addressFactory->create();
        } else {
            $isBillingAddressNew = false;
        }

        foreach ($gigyaMapping as $gigyaName => $confs) {
            /** @var \Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping\ConfItem $conf */
            $value = parent::getValueFromGigyaAccount($gigyaName); // e.g: loginProvider = facebook

            /* If no value found, log and skip field */
            if (is_null($value)) {
                $this->logger->info(__FUNCTION__ . ": Value for {$gigyaName} not found in Gigya user object for Magento user {$account->getId()}. Check your field mapping configuration");
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

                    if ($account instanceof AbstractExtensibleModel) {
                        $account->setCustomAttribute($key, $value);
                    } else {
                        $account->setData($key, $value);
                    }
                } elseif (substr($mageKey, 0, 7) === "address") {
                    $magentoBillingAddress->setData(substr($mageKey, 8), $value);
                } else {
                    $account->setData($mageKey, $value);
                }
            }
        }

        if ($isBillingAddressNew) {
            try {
                $firstname = $magentoBillingAddress->getFirstname();
                $lastname = $magentoBillingAddress->getLastname();

                if (empty($firstname)) {
                    $magentoBillingAddress->setData('firstname', $account->getData('firstname'));
                }

                if (empty($lastname)) {
                    $magentoBillingAddress->setData('lastname', $account->getData('lastname'));
                }

                $magentoBillingAddress->setCustomerId($account->getId());

                /** @var \Magento\Customer\Model\Data\Customer $account */
                $this->addressRepository->save($magentoBillingAddress);
                $account->setDefaultBilling($magentoBillingAddress->getId());

                if (is_null($account->getDefaultShipping())) {
                    $account->setDefaultShipping($magentoBillingAddress->getId());
                }

                $this->logger->debug("Added address {$magentoBillingAddress->getId()} to customer");
            } catch (\Exception $e) {
                $this->logger->debug("Failed to import customer address data: " . $e->getMessage());
            }
        } else {
            try {
                $this->addressRepository->save($magentoBillingAddress);
            } catch (\Exception $ex) {
                $this->logger->error($ex->__toString());
            }
        }
    }

    /**
     * Transform Gigya boolean to Magento boolean - '0'/'1' values
     * @param bool $gigya_bool
     * @return string $magento_bool
     */
    protected function transformGigyaToMagentoBoolean($gigya_bool)
    {
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
     *
     * @param $cmsAccount
     * @param $cmsAccountSaver
     */
    public function saveCmsAccount(&$cmsAccount, $cmsAccountSaver = null)
    {
    }

    /**
     * @param boolean     $skipCache
     *
     * @throws \Exception
     */
    public function retrieveFieldMappings($skipCache = false)
    {
        $conf = false;
        if (!$skipCache) {
            $conf = $this->getMappingFromCache();
        }

        if ($conf === false) {
            $mappingJson = file_get_contents($this->getPath());
            if ($mappingJson === false) {
                $err     = error_get_last();
                $message = "MagentoCustomerFieldsUpdater: Could not retrieve field mapping configuration file. The message was: " . $err['message'];
                throw new \Exception("$message");
            }
            $conf = new fieldMapping\Conf($mappingJson);

            if (!$skipCache) {
                $this->setMappingCache($conf);
            }
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
            $this->gigyaCacheType->save(
                serialize($mappingConf),
                CacheType::TYPE_IDENTIFIER,
                [CacheType::CACHE_TAG],
                86400
            );
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

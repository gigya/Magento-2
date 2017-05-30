<?php
/**
 * Observer for enriching user data. from Gigya to CMS and vice versa
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Gigya\GigyaIM\Model\M2CustomerFieldsUpdater;
use Gigya\GigyaIM\Model\Cache\Type\FieldMapping as CacheType;
use \Gigya\GigyaIM\Logger\Logger;

class MapFieldsObserver implements ObserverInterface
{

    /**
     * @var \Gigya\GigyaIM\Model\M2CustomerFieldsUpdater
     */
    protected $m2CustomerFieldsUpdater;

    protected $_logger;

    protected $_gigyaCacheType;

    protected $scopeConfig;

    /** @var  CustomerRepositoryInterface */
    protected $customerRepository;

    /**
     * MapFieldsObserver constructor.
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        CacheType $gigyaCacheType,
        CustomerRepositoryInterface $customerRepository
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->_logger = $logger;
        $this->_gigyaCacheType = $gigyaCacheType;
        $this->customerRepository = $customerRepository;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $config_file_path = $this->scopeConfig->getValue("gigya_section_fieldmapping/general_fieldmapping/mapping_file_path");
        if (!is_null($config_file_path)) {
            $customer = $observer->getData('customer');
            $gigya_user = $observer->getData('gigya_user');
            $this->m2CustomerFieldsUpdater = new M2CustomerFieldsUpdater($gigya_user, $config_file_path, $this->customerRepository);
            $this->m2CustomerFieldsUpdater->setGigyaLogger($this->_logger);
            // get field mapping from cache or file
            try {
                $this->setFieldMapping();
            } catch (\Gigya\CmsStarterKit\fieldMapping\CmsUpdaterException $e) {
                $message = "error " . $e->getCode() . ". message: " . $e->getMessage() . ". File: " .$e->getFile();
                $this->gigyaLog(
                    "error " . $e->getCode() . ". message: " . $e->getMessage() . ". File: " .$e->getFile(),
                    __CLASS__ , __FUNCTION__
                );
                throw new GigyaFieldMappingException($message);
            }
            try {
                $this->m2CustomerFieldsUpdater->updateCmsAccount($customer);
            } catch (\Exception $e) {
                $message = "error " . $e->getCode() . ". message: " . $e->getMessage() . ". File: " .$e->getFile();
                $this->gigyaLog(
                    $message,
                    __CLASS__ , __FUNCTION__
                );
                throw new GigyaFieldMappingException($message);
            }
        } else {
            $message = "mapping fields file path is not defined. Define file path at: Stores:Config:Gigya:Field Mapping";
            $this->gigyaLog(
                $message,
                __CLASS__ , __FUNCTION__
            );
            throw new GigyaFieldMappingException($message);
        }
    }

    /**
     * Get field mapping from cache or file:
     *  check if mapping is in cache
     *  if yes, retrieve it and set parent gigyaMapping
     *  In not, run parent retrieveFieldMappings()
     */
    protected function setFieldMapping() {
        if ($mapping = $this->_gigyaCacheType->load(CacheType::TYPE_IDENTIFIER)) {
            $this->m2CustomerFieldsUpdater->setGigyaMapping(unserialize($mapping));
        } else {
            $this->m2CustomerFieldsUpdater->retrieveFieldMappings();
            $this->_gigyaCacheType->save(serialize($this->m2CustomerFieldsUpdater->getGigyaMapping()),
                CacheType::TYPE_IDENTIFIER, [CacheType::CACHE_TAG], 86400);
        }
    }

    protected function gigyaLog($message, $class, $method) {
        $this->_logger->warning(
            $message,
            array( "class" => $class,  "method" => $method )
        );
    }
}
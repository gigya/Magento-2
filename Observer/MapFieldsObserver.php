<?php
/**
 * Observer for enriching user data. from Gigya to CMS and vice versa
 */

namespace Gigya\GigyaIM\Observer;

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

    /**
     * MapFieldsObserver constructor.
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        CacheType $gigyaCacheType
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->_logger = $logger;
        $this->_gigyaCacheType = $gigyaCacheType;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $config_file_path = $this->scopeConfig->getValue("gigya_section_fieldmapping/general_fieldmapping/mapping_file_path");
        if (!is_null($config_file_path)) {
            $customer = $observer->getData('customer');
            $gigya_user = $observer->getData('gigya_user');
            $accountManagement = $observer->getData('accountManagement');
            $this->m2CustomerFieldsUpdater = new M2CustomerFieldsUpdater($gigya_user, $config_file_path);
            $this->m2CustomerFieldsUpdater->setGigyaLogger($this->_logger);
            // get field mapping from cache or file
            try {
                $this->setFieldMapping();
            } catch (\Gigya\CmsStarterKit\fieldMapping\CmsUpdaterException $e) {
                $this->gigyaLog(
                    "error " . $e->getCode() . ". message: " . $e->getMessage() . ". File: " .$e->getFile(),
                    __CLASS__ , __FUNCTION__
                );
                return;
            }
            try {
                $this->m2CustomerFieldsUpdater->updateCmsAccount($customer, $accountManagement);
            } catch (\Exception $e) {
                $this->gigyaLog(
                    "error " . $e->getCode() . ". message: " . $e->getMessage() . ". File: " .$e->getFile(),
                    __CLASS__ , __FUNCTION__
                );
                return;
            }
        } else {
            $this->gigyaLog(
                "mapping fields file path is not defined. Define file path at: Stores:Config:Gigya:Field Mapping",
                __CLASS__ , __FUNCTION__
            );
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
<?php
/**
 *
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Gigya\GigyaIM\Model\MagentoCustomerFieldsUpdater;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * GigyaToMagentoFieldMapping
 *
 * Observer for mapping Gigya's account data to Magento Customer entity.
 *
 */
class GigyaToMagentoFieldMapping implements ObserverInterface
{
    /**
     * @var MagentoCustomerFieldsUpdater
     */
    protected $customerFieldsUpdater;

    /** @var GigyaLogger  */
    protected $logger;

    /** @var ScopeConfigInterface  */
    protected $scopeConfig;

    /**
     * GigyaToMagentoFieldMapping constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param GigyaLogger $logger
     * @param MagentoCustomerFieldsUpdater $customerFieldsUpdater
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        GigyaLogger $logger,
        MagentoCustomerFieldsUpdater $customerFieldsUpdater
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->customerFieldsUpdater = $customerFieldsUpdater;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $config_file_path = $this->scopeConfig->getValue("gigya_section_fieldmapping/general_fieldmapping/mapping_file_path");
        if (!is_null($config_file_path)) {
            $customer = $observer->getData('customer');
            $gigya_user = $observer->getData('gigya_user');
            $this->customerFieldsUpdater->setPath($config_file_path);
            $this->customerFieldsUpdater->setGigyaUser($gigya_user);
            try {
                $this->customerFieldsUpdater->updateCmsAccount($customer);
            } catch (\Exception $e) {
                $message = "error " . $e->getCode() . ". message: " . $e->getMessage() . ". File: " .$e->getFile();
                $this->logger->error(
                    $message,
                    [
                        'class' => __CLASS__,
                        'function' => __FUNCTION__
                    ]
                );
                throw new GigyaFieldMappingException($message);
            }
        } else {
            $message = "mapping fields file path is not defined. Define file path at: Stores:Config:Gigya:Field Mapping";
            $this->logger->error(
                $message,
                [
                    'class' => __CLASS__,
                    'function' => __FUNCTION__
                ]
            );
            throw new GigyaFieldMappingException($message);
        }
    }
}
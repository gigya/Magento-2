<?php
/**
 *
 */

namespace Gigya\GigyaIM\Observer;

use Gigya\CmsStarterKit\fieldMapping\CmsUpdaterException;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Model\GigyaCustomerFieldsUpdater;
use Magento\Customer\Model\Data\Customer;
use Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * GigyaFromMagentoFieldMapping
 *
 * Observer for mapping Magento Customer's entity data to Gigya data.
 *
 */
class GigyaFromMagentoFieldMapping implements ObserverInterface
{
    /**
     * @var \Gigya\GigyaIM\Model\GigyaCustomerFieldsUpdater
     */
    protected $customerFieldsUpdater;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /** @var ScopeConfigInterface  */
    protected $scopeConfig;

    /**
     * MagentoToGigyaFieldMapping constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param GigyaCustomerFieldsUpdater $customerFieldsUpdater
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        GigyaCustomerFieldsUpdater $customerFieldsUpdater
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
            /** @var Customer $magentoCustomer */
            $magentoCustomer = $observer->getData('customer');
            /** @var GigyaUser $gigyaUser */
            $gigyaUser = $observer->getData('gigya_user');

            $this->customerFieldsUpdater->setMagentoCustomer($magentoCustomer);
            $this->customerFieldsUpdater->setGigyaUser($gigyaUser);
            $this->customerFieldsUpdater->setPath($config_file_path);

            try {
                $this->customerFieldsUpdater->updateGigya();
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
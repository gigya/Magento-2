<?php
/**
 *
 */

namespace Gigya\GigyaIM\Model\FieldMapping;

use Magento\Customer\Model\Data\Customer;
use Gigya\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Model\GigyaCustomerFieldsUpdater;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

/**
 * GigyaFromMagentoFieldMapping
 *
 * Observer for mapping Magento Customer's entity data to Gigya data.
 *
 */
class GigyaFromMagento
{
    /**
     * @var \Gigya\GigyaIM\Model\GigyaCustomerFieldsUpdater
     */
    protected $customerFieldsUpdater;

    /**
     * @var GigyaLogger
     */
    protected $logger;

    /** @var ScopeConfigInterface  */
    protected $scopeConfig;

    /**
     * MagentoToGigyaFieldMapping constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param GigyaLogger $logger
     * @param GigyaCustomerFieldsUpdater $customerFieldsUpdater
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        GigyaLogger $logger,
        GigyaCustomerFieldsUpdater $customerFieldsUpdater
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->customerFieldsUpdater = $customerFieldsUpdater;
    }

    /**
     * @param Customer $customer
     * @param GigyaUser $gigyaUser
     * @throws GigyaFieldMappingException
     */
    public function run($customer, $gigyaUser)
    {
        $config_file_path = $this->scopeConfig->getValue("gigya_section_fieldmapping/general_fieldmapping/mapping_file_path");
        if (!is_null($config_file_path)) {
            $this->customerFieldsUpdater->setMagentoCustomer($customer);
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
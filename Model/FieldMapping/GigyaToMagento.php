<?php
/**
 *
 */

namespace Gigya\GigyaIM\Model\FieldMapping;

use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Gigya\GigyaIM\Model\MagentoCustomerFieldsUpdater;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use \Magento\Customer\Model\Customer;

/**
 * GigyaToMagento
 *
 * Mapping of Gigya's account data to a Magento Customer entity, based on a json mapping file.
 *
 */
class GigyaToMagento
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

    /**
     * Performs the mapping from Gigya account to Magento Customer entity.
     *
     * The mapping rules are retrieved from the json field mapping file pointed to by backend configuration key 'gigya_section_fieldmapping/general_fieldmapping/mapping_file_path'
     *
     * @param Customer $customer
     * @param $gigyaUser
     * @throws GigyaFieldMappingException
     */
    public function run($customer, $gigyaUser)
    {
        $config_file_path = $this->scopeConfig->getValue("gigya_section_fieldmapping/general_fieldmapping/mapping_file_path");
        if (!is_null($config_file_path)) {
            $this->customerFieldsUpdater->setPath($config_file_path);
            $this->customerFieldsUpdater->setGigyaUser($gigyaUser);
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
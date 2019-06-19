<?php

namespace Gigya\GigyaIM\Model\FieldMapping;

use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Magento\Customer\Api\Data\CustomerInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Gigya\GigyaIM\Model\MagentoCustomerFieldsUpdater;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Customer\Model\Data\Customer;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

/**
 * GigyaToMagento
 *
 * Mapping of Gigya's account data to a Magento Customer entity, based on a json mapping file.
 *
 */
class GigyaToMagento extends AbstractFieldMapping
{
    /**
     * @var MagentoCustomerFieldsUpdater
     */
    protected $customerFieldsUpdater;

    /** @var ScopeConfigInterface  */
    protected $scopeConfig;

	/**
	 * GigyaToMagentoFieldMapping constructor.
	 *
	 * @param ScopeConfigInterface         $scopeConfig
	 * @param GigyaLogger                  $logger
	 * @param MagentoCustomerFieldsUpdater $customerFieldsUpdater
	 * @param ModuleDirReader              $moduleDirReader
	 */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        GigyaLogger $logger,
        MagentoCustomerFieldsUpdater $customerFieldsUpdater,
        ModuleDirReader $moduleDirReader
    )
    {
        parent::__construct($scopeConfig, $moduleDirReader, $logger);
        $this->customerFieldsUpdater = $customerFieldsUpdater;
    }

    /**
     * Performs the mapping from Gigya account to Magento Customer entity.
     *
     * The mapping rules are retrieved from the json field mapping file pointed to by backend configuration key 'gigya_section_fieldmapping/general_fieldmapping/mapping_file_path'
     *
	 * @param Customer|CustomerInterface $customer
	 * @param array                      $gigyaUser
	 * @param boolean                    $skipCache
     *
     * @throws GigyaFieldMappingException
     */
    public function run($customer, $gigyaUser, $skipCache = false)
    {
        $config_file_path = $this->getFieldMappingFilePath();
        if ($config_file_path != null) {
            $this->customerFieldsUpdater->setPath($config_file_path);
            $this->customerFieldsUpdater->setGigyaUser($gigyaUser);
            $this->customerFieldsUpdater->setMagentoUser($customer);
            try {
                $this->customerFieldsUpdater->updateCmsAccount($customer, null, $skipCache);
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
            $message = "Mapping fields file path is not defined. Define file path at: Stores > Config > Gigya > Field Mapping";
            $this->logger->warn(
                $message,
                [
                    'class' => __CLASS__,
                    'function' => __FUNCTION__
                ]
            );
        }
    }
}
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
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

/**
 * GigyaFromMagentoFieldMapping
 *
 * Observer for mapping Magento Customer's entity data to Gigya data.
 *
 */
class GigyaFromMagento extends AbstractFieldMapping
{
    /**
     * @var \Gigya\GigyaIM\Model\GigyaCustomerFieldsUpdater
     */
    protected $customerFieldsUpdater;

    /** @var ScopeConfigInterface  */
    protected $scopeConfig;

    /**
     * GigyaFromMagento constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param GigyaLogger $logger
     * @param GigyaCustomerFieldsUpdater $customerFieldsUpdater
     * @param ModuleDirReader $moduleDirReader
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        GigyaLogger $logger,
        GigyaCustomerFieldsUpdater $customerFieldsUpdater,
        ModuleDirReader $moduleDirReader
    )
    {
        parent::__construct($scopeConfig, $moduleDirReader, $logger);
        $this->customerFieldsUpdater = $customerFieldsUpdater;
    }

    /**
     * @param Customer $customer
     * @param GigyaUser $gigyaUser
     * @throws GigyaFieldMappingException
     */
    public function run($customer, $gigyaUser)
    {
        $config_file_path = $this->getFieldMappingFile();
        if ($config_file_path != null) {
            $this->customerFieldsUpdater->setMagentoUser($customer);
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
            $this->logger->warn(
                $message,
                [
                    'class' => __CLASS__,
                    'function' => __FUNCTION__
                ]
            );
        }
    }

    /**
     * Get data file fieldMapping
     * @return mixed|string
     */
    public function getFieldsMappingFile(){
        return $this->getFieldMappingFile();
    }

    /**
     * Get magento custom attribute user overide by observer DefaultGigyaSyncFieldMapping
     * @return \Magento\Framework\Api\AttributeInterface[]|null
     */
    public function getMagentoUserObserver(){
        return $this->customerFieldsUpdater->getMagentoUser()->getCustomAttributes();
    }
}
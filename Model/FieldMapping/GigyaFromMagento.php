<?php

namespace Gigya\GigyaIM\Model\FieldMapping;

use Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException;
use Magento\Customer\Model\Data\Customer;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Exception\GigyaFieldMappingException;
use Gigya\GigyaIM\Model\GigyaCustomerFieldsUpdater;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

/**
 * GigyaFromMagentoFieldMapping
 *
 * Observer for mapping Magento Customer's entity data to Gigya data
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
     * GigyaFromMagento constructor
	 *
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
	 * @param $isGigyaException
	 * @param \Exception|GSApiException $exception
	 *
	 * @throws GigyaFieldMappingException
	 */
    public function handleExceptions($isGigyaException, $exception) {
    	$isGigyaException = ($isGigyaException and ($exception instanceof GSApiException));
    	$message = sprintf("Error %s. Message: %s. %sFile: %s",
						   $exception->getCode(),
						   ($isGigyaException) ? $exception->getLongMessage() : $exception->getMessage(),
						   ($isGigyaException) ? "Gigya call ID: {$exception->getCallId()}. " : '',
						   $exception->getFile()
		);

		$this->logger->error(
			$message,
			[
				'class' => __CLASS__,
				'function' => __FUNCTION__
			]
		);
		throw new GigyaFieldMappingException($message);
	}

    /**
     * @param Customer $customer
     * @param GigyaUser $gigyaUser
	 *
     * @throws GigyaFieldMappingException
     */
    public function run($customer, $gigyaUser)
    {
		$config_file_path = $this->getFieldMappingFilePath();
        if ($config_file_path != null) {
            $this->customerFieldsUpdater->setMagentoUser($customer);
            $this->customerFieldsUpdater->setGigyaUser($gigyaUser);
            $this->customerFieldsUpdater->setPath($config_file_path);

            try
			{
				$this->customerFieldsUpdater->updateGigya();
			} catch (GSApiException $e) {
				$this->handleExceptions(true, $e);
            } catch (\Exception $e) {
				$this->handleExceptions(false, $e);
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

    /**
     * Get magento custom attribute user override by observer DefaultGigyaSyncFieldMapping
     * @return \Magento\Framework\Api\AttributeInterface[]|null
     */
    public function getMagentoUserObserver(){
        return $this->customerFieldsUpdater->getMagentoUser()->getCustomAttributes();
    }
}
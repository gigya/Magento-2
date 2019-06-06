<?php

namespace Gigya\GigyaIM\Model\FieldMapping;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Gigya\GigyaIM\Logger\Logger as GigyaLogger;

class AbstractFieldMapping
{
    /** @var ScopeConfigInterface  */
    protected $scopeConfig;

    /**
     * @var GigyaLogger
     */
    protected $logger;

    /**
     * @var ModuleDirReader
     */
    protected $moduleDirReader;

    /**
     * MagentoToGigyaFieldMapping constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ModuleDirReader $moduleDirReader
	 * @param GigyaLogger $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ModuleDirReader $moduleDirReader,
        GigyaLogger $logger
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->moduleDirReader = $moduleDirReader;
        $this->logger = $logger;
    }

	protected function getFieldMappingFilePath()
	{
		$config_file_path = $this->scopeConfig->getValue("gigya_section_fieldmapping/general_fieldmapping/mapping_file_path");

		if ($config_file_path) {
			return $config_file_path;
		} else {
			$this->logger->alert(__('No Field Mapping file provided. Reverting to the default one.'));

			return $this->moduleDirReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Gigya_GigyaIM') .
				DIRECTORY_SEPARATOR . 'json' . DIRECTORY_SEPARATOR . 'default_field_mapping.json';
		}
	}
}
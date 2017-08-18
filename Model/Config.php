<?php
namespace Gigya\GigyaIM\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class Config
 * @package Gigya\GigyaIM\Model
 */
class Config
{
    const SESSION_MODE_FIXED = 0;
    const SESSION_MODE_EXTENDED = 1;

    const XML_PATH_SESSION_MODE = 'gigya_session/session/mode';
    const XML_PATH_SESSION_EXPIRATION = 'gigya_session/session/expiration';

    const XML_PATH_MAPPING_FILE_PATH = 'gigya_section_fieldmapping/general_fieldmapping/mapping_file_path';

    const XML_PATH_GENERAL = 'gigya_section/general';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return int
     */
    public function getSessionMode()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_SESSION_MODE);
    }

    /**
     * @return int
     */
    public function getSessionExpiration()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_SESSION_EXPIRATION);
    }

    /**
     * @return string
     */
    public function getMappingFilePath()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_MAPPING_FILE_PATH);
    }

    /**
     * @return array
     */
    public function getGigyaGeneralConfig()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_GENERAL);
    }
}
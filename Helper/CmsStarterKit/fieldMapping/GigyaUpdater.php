<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping;

use DateTime;
use Exception;
use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;
use Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException;

abstract class GigyaUpdater
{
    /**
     * @var array
     */
    private array $cmsMappings;

    /**
     * @var array
     */
    private mixed $cmsArray;

    /**
     * @var string
     */
    private string $gigyaUid;

    /**
     * @var bool
     */
    private bool $mapped;

    /**
     * @var string
     */
    private string $path;

    /**
     * @var array
     */
    private array $gigyaArray;

    /**
     * @var GigyaApiHelper
     */
    private GigyaApiHelper $apiHelper;

    /**
     * GigyaUpdater constructor.
     *
     * @param $cmsValuesArray
     * @param $gigyaUid
     * @param $path
     * @param $apiHelper
     */
    public function __construct(
        $cmsValuesArray,
        $gigyaUid,
        $path,
        $apiHelper
    ) {
        $this->cmsArray  = (!empty($cmsValuesArray)) ? $cmsValuesArray : [];
        $this->gigyaUid  = (string)$gigyaUid;
        $this->path      = (string) $path;
        $this->mapped    = !empty($this->path);
        $this->apiHelper = $apiHelper;
    }

    /**
     * @throws FieldMappingException
     * @throws Exception
     * @throws GSApiException
     */
    public function updateGigya(): void
    {
        $this->retrieveFieldMappings();
        $this->callCmsHook();
        $this->gigyaArray = $this->createGigyaArray();
        $this->apiHelper->updateGigyaAccount($this->gigyaUid, $this->gigyaArray);
    }

    /**
     * @return boolean
     */
    public function isMapped(): bool
    {
        return $this->mapped;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath($path): void
    {
        $this->path = $path;
    }

    /**
     * @return mixed
     */
    public function getCmsArray(): mixed
    {
        return $this->cmsArray;
    }

    /**
     * @param mixed $cmsArray
     */
    public function setCmsArray($cmsArray): void
    {
        $this->cmsArray = $cmsArray;
    }

    /**
     * @param string $uid
     */
    public function setGigyaUid($uid): void
    {
        $this->gigyaUid = $uid;
    }

    /**
     * @return mixed
     */
    public function getGigyaArray(): mixed
    {
        return $this->gigyaArray;
    }

    /**
     * @param mixed $gigyaArray
     */
    public function setGigyaArray($gigyaArray): void
    {
        $this->gigyaArray = $gigyaArray;
    }

    /**
     * A function that calls a cms hook for example in magento 1
     * Mage::dispatchEvent("pre_sync_to_gigya", array("updater" => $this));
     */
    abstract protected function callCmsHook();

    /**
     * Puts the field mapping configuration in cache.
     * on error throws FieldMappingException
     *
     * @param Conf $mappingConf
     *
     * @throws FieldMappingException
     */
    abstract protected function setMappingCache($mappingConf);

    /**
     * Retrieves the field mapping object from cache.
     * if no mapping is found or there is an error returns false.
     *
     * @return mixed
     */
    abstract protected function getMappingFromCache(): mixed;

    /**
     * @throws FieldMappingException
     * @throws Exception
     */
    protected function retrieveFieldMappings(): void
    {
        $conf = $this->getMappingFromCache();
        if (false === $conf) {
            $mappingJson = file_get_contents($this->path);
            if (false === $mappingJson) {
                $err     = error_get_last();
                $message = "GigyaUpdater: Could not retrieve field mapping configuration file. The message was: " . $err['message'];
                throw new Exception($message);
            }

            $conf = new Conf($mappingJson);
            $this->setMappingCache($conf);
        }

        $this->cmsMappings = $conf->getCmsKeyed();
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function createGigyaArray(): array
    {
        $gigyaArray = [];
        foreach ($this->cmsArray as $key => $value) {
            /** @var ConfItem $conf */
            $confs = $this->cmsMappings[$key];
            foreach ($confs as $conf) {
                $value = $this->castVal($value, $conf);
                if (!is_null($value)) {
                    $this->assignArrayByPath($gigyaArray, $conf->getGigyaName(), $value);
                }
            }
        }

        return $gigyaArray;
    }

    /**
     * @param mixed $val
     * @param ConfItem $conf
     *
     * @return mixed $val;
     * @throws Exception
     */
    private function castVal(mixed $val, $conf): mixed
    {
        switch ($conf->getGigyaType()) {
            case "string":
            case "text":
            case "varchar":
                return (string) $val;
            case "long";
            case "integer":
            case "int":
                return (int) $val;
            case "boolean":
            case "bool":
                if (is_string($val)) {
                    $val = strtolower($val);
                }

                return filter_var($val, FILTER_VALIDATE_BOOLEAN);
            case 'date':
                if ($val and !preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|(\+|-)\d{2}(:?\d{2})?)/', $val)) {
                    $datetime = new DateTime($val);
                    // Return date in format ISO 8601 (https://en.wikipedia.org/wiki/ISO_8601)
                    $val = $datetime->format('c');
                }

                return $val;
            default:
                return $val;
        }
    }

    private function assignArrayByPath(&$arr, $path, $value, $separator = '.'): void
    {
        $keys = explode($separator, $path);

        foreach ($keys as $key) {
            if (!array_key_exists($key, $arr)) {
                $arr[$key] = [];
            }

            $arr = &$arr[$key];
        }

        $arr = $value;
    }
}

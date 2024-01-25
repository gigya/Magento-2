<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping;

use Gigya\GigyaIM\Helper\CmsStarterKit\User\GigyaUser;

abstract class CmsUpdater
{
    /**
     * @var GigyaUser
     */
    private $gigyaUser;

    private $gigyaMapping;

    /**
     * @var bool
     */
    private $mapped = false;

    private $path;

    /**
     * CmsUpdater constructor
     *
     * @param GigyaUser $gigyaAccount
     * @param string $mappingFilePath
     */
    public function __construct($gigyaAccount, $mappingFilePath)
    {
        $this->gigyaUser = $gigyaAccount;
        $this->path      = (string) $mappingFilePath;
        $this->mapped    = !empty($this->path);
    }

    /**
     * @param mixed       $cmsAccount
     * @param             $cmsAccountSaver
     * @param boolean      $skipCache        Determines whether to skip the caching and cache retrieval for field mapping
     *
     * @throws CmsUpdaterException
     */
    public function updateCmsAccount(&$cmsAccount, $cmsAccountSaver = null, $skipCache = false): void
    {
        if (!isset($this->gigyaMapping)) {
            $this->retrieveFieldMappings($skipCache);
        }

        if (method_exists($this, 'callCmsHook')) {
            $this->callCmsHook();
        }

        $this->setAccountValues($cmsAccount);
        $this->saveCmsAccount($cmsAccount, $cmsAccountSaver);
    }

    /**
     * @return boolean
     */
    public function isMapped(): bool
    {
        return $this->mapped;
    }

    abstract protected function callCmsHook();

    abstract protected function saveCmsAccount(&$cmsAccount, $cmsAccountSaver);

    /**
     * @param boolean     $skipCache
     *
     * @throws CmsUpdaterException
     */
    public function retrieveFieldMappings($skipCache = false): void
    {
        if (file_exists($this->path)) {
            $mappingJson = file_get_contents($this->path);
        } else {
            throw new CmsUpdaterException("Field Mapping file could not be found at " . $this->path);
        }

        if ($mappingJson === false) {
            $err     = error_get_last();
            $message = "CMSUpdater: Could not retrieve field mapping configuration file. The message was: " . $err['message'];
            throw new CmsUpdaterException("$message");
        }

        $conf               = new Conf($mappingJson);
        $this->gigyaMapping = $conf->getGigyaKeyed();
    }

    /**
     * @param mixed $account
     */
    abstract protected function setAccountValues(&$account);

    /**
     * @param $path
     *
     * @return GigyaUser|null|string
     */
    public function getValueFromGigyaAccount($path): GigyaUser|string|null
    {
        $userData = $this->getGigyaUser();
        $value    = $userData->getNestedValue($path);

        return $value;
    }

    /**
     * @param mixed    $value
     * @param ConfItem $conf
     *
     * @return mixed
     */
    protected function castValue($value, $conf): mixed
    {
        switch ($conf->getCmsType()) {
            case "decimal":
                $value = (float) $value;
                break;
            case "int":
            case "integer":
                $value = (int) $value;
                break;
            case "text":
            case "string":
            case "varchar":
                $value = (string) $value;
                break;
            case "bool":
            case "boolean":
                $value = boolval($value); /* PHP 5.5+ */
                break;
        }

        return $value;
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
     * @return GigyaUser
     */
    public function getGigyaUser(): GigyaUser
    {
        return $this->gigyaUser;
    }

    /**
     * @param array $gigyaUser
     */
    public function setGigyaUser($gigyaUser): void
    {
        $this->gigyaUser = $gigyaUser;
    }

    /**
     * @return mixed
     */
    public function getGigyaMapping(): mixed
    {
        return $this->gigyaMapping;
    }

    /**
     * @param mixed $gigyaMapping
     */
    public function setGigyaMapping($gigyaMapping): void
    {
        $this->gigyaMapping = $gigyaMapping;
    }
}

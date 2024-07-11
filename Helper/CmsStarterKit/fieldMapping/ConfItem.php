<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaJsonObject;

class ConfItem
{

    /**
     * @var string
     */
    protected string $cmsName;

    /**
     * @var string
     */
    protected string $cmsType;

    /**
     * @var string
     */
    protected string $gigyaName;

    /**
     * @var string
     */
    protected string $gigyaType;

    /**
     * @var string
     */
    protected string $direction = "g2cms";

    /**
     * @var array
     */
    protected array $custom;

    /**
     * ConfItem constructor.
     *
     * @param array $array
     */
    public function __construct($array)
    {
        foreach ($array as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * @return string
     */
    public function getCmsName(): string
    {
        return $this->cmsName;
    }

    /**
     * @param string $cmsName
     */
    public function setCmsName($cmsName): void
    {
        $this->cmsName = $cmsName;
    }

    /**
     * @return string
     */
    public function getCmsType(): string
    {
        return $this->cmsType;
    }

    /**
     * @param string $cmsType
     */
    public function setCmsType($cmsType): void
    {
        $this->cmsType = $cmsType;
    }

    /**
     * @return string
     */
    public function getGigyaName(): string
    {
        return $this->gigyaName;
    }

    /**
     * @param string $gigyaName
     */
    public function setGigyaName($gigyaName): void
    {
        $this->gigyaName = $gigyaName;
    }

    /**
     * @return string
     */
    public function getGigyaType(): string
    {
        return $this->gigyaType;
    }

    /**
     * @param string $gigyaType
     */
    public function setGigyaType($gigyaType): void
    {
        $this->gigyaType = $gigyaType;
    }

    /**
     * @return array
     */
    public function getCustom(): array
    {
        return $this->custom;
    }

    /**
     * @param GigyaJsonObject $custom
     */
    public function setCustom($custom): void
    {
        $this->custom = $custom;
    }
}

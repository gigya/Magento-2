<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaJsonObject;

class ConfItem
{

	/**
	 * @var string
	 */
	protected $cmsName;

	/**
	 * @var string
	 */
	protected $cmsType;

	/**
	 * @var string
	 */
	protected $gigyaName;

	/**
	 * @var string
	 */
	protected $gigyaType;

	/**
	 * @var string
	 */
	protected $direction = "g2cms";

	/**
	 * @var array
	 */
	protected $custom;

	/**
	 * ConfItem constructor.
	 *
	 * @param array $array
	 */
	public function __construct($array) {
		foreach ($array as $key => $value)
		{
			$this->$key = $value;
		}
	}

	/**
	 * @return string
	 */
	public function getCmsName() {
		return $this->cmsName;
	}

	/**
	 * @param string $cmsName
	 */
	public function setCmsName($cmsName) {
		$this->cmsName = $cmsName;
	}

	/**
	 * @return string
	 */
	public function getCmsType() {
		return $this->cmsType;
	}

	/**
	 * @param string $cmsType
	 */
	public function setCmsType($cmsType) {
		$this->cmsType = $cmsType;
	}

	/**
	 * @return string
	 */
	public function getGigyaName() {
		return $this->gigyaName;
	}

	/**
	 * @param string $gigyaName
	 */
	public function setGigyaName($gigyaName) {
		$this->gigyaName = $gigyaName;
	}

	/**
	 * @return string
	 */
	public function getGigyaType() {
		return $this->gigyaType;
	}

	/**
	 * @param string $gigyaType
	 */
	public function setGigyaType($gigyaType) {
		$this->gigyaType = $gigyaType;
	}

	/**
	 * @return array
	 */
	public function getCustom() {
		return $this->custom;
	}

	/**
	 * @param GigyaJsonObject $custom
	 */
	public function setCustom($custom) {
		$this->custom = $custom;
	}


}
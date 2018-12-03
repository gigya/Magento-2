<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping;

class Conf
{
	private $cmsKeyed;

	private $gigyaKeyed;

	private $mappingConf;

	public function __construct($json) {
		$this->mappingConf = json_decode($json, true);
	}

	/**
	 * @return array
	 */
	public function getCmsKeyed() {
		if (empty($this->cmsKeyed))
		{
			$this->buildKeyedArrays($this->mappingConf);
		}

		return $this->cmsKeyed;
	}

	protected function buildKeyedArrays($array) {
		$cmsKeyedArray   = [];
		$gigyaKeyedArray = [];

		foreach ($array as $confItem)
		{
			$cmsKey    = $confItem['cmsName'];
			$gigyaKey  = $confItem['gigyaName'];
			$direction = isset($confItem['direction']) ? $confItem['direction'] : "g2cms";
			$conf      = new ConfItem($confItem);
			switch ($direction)
			{
				case "cms2g":
					$cmsKeyedArray[$cmsKey][] = $conf;
					break;
				case "both":
					$gigyaKeyedArray[$gigyaKey][] = $conf;
					$cmsKeyedArray[$cmsKey][]     = $conf;
					break;
				default:
					$gigyaKeyedArray[$gigyaKey][] = $conf;
					break;
			}
		}

		$this->gigyaKeyed = $gigyaKeyedArray;
		$this->cmsKeyed   = $cmsKeyedArray;
	}

	/**
	 * @return array
	 */
	public function getGigyaKeyed() {
		if (empty($this->gigyaKeyed))
		{
			$this->buildKeyedArrays($this->mappingConf);
		}

		return $this->gigyaKeyed;
	}

	/**
	 * @return array
	 */
	public function getMappingConf() {
		return $this->mappingConf;
	}

	public function __toString() {
		return json_encode(get_object_vars($this));
	}
}
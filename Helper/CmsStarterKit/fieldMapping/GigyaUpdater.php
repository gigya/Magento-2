<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;

abstract class GigyaUpdater
{
	/**
	 * @var array
	 */
	private $cmsMappings;

	/**
	 * @var array
	 */
	private $cmsArray;

	/**
	 * @var string
	 */
	private $gigyaUid;

	/**
	 * @var bool
	 */
	private $mapped;

	/**
	 * @var string
	 */
	private $path;

	/**
	 * @var array
	 */
	private $gigyaArray;

	/**
	 * @var GigyaApiHelper
	 */
	private $apiHelper;

	/**
	 * GigyaUpdater constructor.
	 *
	 * @param $cmsValuesArray
	 * @param $gigyaUid
	 * @param $path
	 * @param $apiHelper
	 */
	public function __construct($cmsValuesArray, $gigyaUid, $path, $apiHelper) {
		$this->cmsArray  = (!empty($cmsValuesArray)) ? $cmsValuesArray : [];
		$this->gigyaUid  = $gigyaUid;
		$this->path      = (string) $path;
		$this->mapped    = !empty($this->path);
		$this->apiHelper = $apiHelper;
	}

	/**
	 * @throws FieldMappingException
	 * @throws \Exception
	 * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException
	 */
	public function updateGigya() {
		$this->retrieveFieldMappings();
		$this->callCmsHook();
		$this->gigyaArray = $this->createGigyaArray();
		$this->apiHelper->updateGigyaAccount($this->gigyaUid, $this->gigyaArray);
	}

	/**
	 * @return boolean
	 */
	public function isMapped() {
		return $this->mapped;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @param string $path
	 */
	public function setPath($path) {
		$this->path = $path;
	}

	/**
	 * @return mixed
	 */
	public function getCmsArray() {
		return $this->cmsArray;
	}

	/**
	 * @param mixed $cmsArray
	 */
	public function setCmsArray($cmsArray) {
		$this->cmsArray = $cmsArray;
	}

	/**
	 * @param string $uid
	 */
	public function setGigyaUid($uid) {
		$this->gigyaUid = $uid;
	}

	/**
	 * @return mixed
	 */
	public function getGigyaArray() {
		return $this->gigyaArray;
	}

	/**
	 * @param mixed $gigyaArray
	 */
	public function setGigyaArray($gigyaArray) {
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
	abstract protected function getMappingFromCache();

	/**
	 * @throws FieldMappingException
	 * @throws \Exception
	 */
	protected function retrieveFieldMappings() {
		$conf = $this->getMappingFromCache();
		if (false === $conf)
		{
			$mappingJson = file_get_contents($this->path);
			if (false === $mappingJson)
			{
				$err     = error_get_last();
				$message = "GigyaUpdater: Could not retrieve field mapping configuration file. The message was: " . $err['message'];
				throw new \Exception($message);
			}

			$conf = new Conf($mappingJson);
			$this->setMappingCache($conf);
		}

		$this->cmsMappings = $conf->getCmsKeyed();
	}

	/**
	 * @return array
	 */
	protected function createGigyaArray() {
		$gigyaArray = [];
		foreach ($this->cmsArray as $key => $value)
		{
			/** @var ConfItem $conf */
			$confs = $this->cmsMappings[$key];
			foreach ($confs as $conf)
			{
				$value = $this->castVal($value, $conf);
				if (!is_null($value))
				{
					$this->assignArrayByPath($gigyaArray, $conf->getGigyaName(), $value);
				}
			}
		}

		return $gigyaArray;
	}

	/**
	 * @param mixed    $val
	 * @param ConfItem $conf
	 *
	 * @return mixed $val;
	 */
	private function castVal($val, $conf) {
		switch ($conf->getGigyaType())
		{
			case "string":
			case "text":
			case "varchar":
				return (string) $val;
				break;
			case "long";
			case "integer":
			case "int":
				return (int) $val;
				break;
			case "boolean":
			case "bool":
				if (is_string($val))
				{
					$val = strtolower($val);
				}

				return filter_var($val, FILTER_VALIDATE_BOOLEAN);
				break;
			case 'date':
				if ($val and !preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|(\+|-)\d{2}(:?\d{2})?)/', $val))
				{
					$datetime = new \DateTime($val);
					// Return date in format ISO 8601 (https://en.wikipedia.org/wiki/ISO_8601)
					$val = $datetime->format('c');
				}

				return $val;
				break;
			default:
				return $val;
				break;
		}
	}

	private function assignArrayByPath(&$arr, $path, $value, $separator = '.') {
		$keys = explode($separator, $path);

		foreach ($keys as $key)
		{
			if (!array_key_exists($key, $arr))
			{
				$arr[$key] = [];
			}

			$arr = &$arr[$key];
		}

		$arr = $value;
	}
}
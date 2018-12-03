<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\ds;

class DsFactory
{
	private $apiHelper;

	/**
	 * DsFactory constructor.
	 *
	 * @param $helper
	 *
	 */
	public function __construct($helper) {
		$this->apiHelper;
	}

	/**
	 * @param $query
	 *
	 * @return DsQueryObject
	 */
	public function createDsqFromQuery($query) {
		$dsQueryObj = new DsQueryObject($this->apiHelper);
		$dsQueryObj->setQuery($query);

		return $dsQueryObj;
	}

	/**
	 * @param $type
	 * @param $fields
	 *
	 * @return DsQueryObject
	 */
	public function createDsqFromFields($type, $fields) {
		$dsQueryObj = new DsQueryObject($this->apiHelper);
		$dsQueryObj->setFields($fields);
		$dsQueryObj->setTable($type);

		return $dsQueryObj;
	}

	/**
	 * @param        $type
	 * @param        $fields
	 * @param        $where
	 * @param        $op
	 * @param        $value
	 * @param string $valueType
	 *
	 * @return DsQueryObject
	 * @throws DsQueryException
	 */
	public function createDsqFromWhere($type, $fields, $where, $op, $value, $valueType = "string") {
		$dsQueryObj = new DsQueryObject($this->apiHelper);
		$dsQueryObj->setFields($fields);
		$dsQueryObj->setTable($type);
		$dsQueryObj->addWhere($where, $op, $value, $valueType);

		return $dsQueryObj;
	}

	/**
	 * @param $oid
	 * @param $type
	 *
	 * @return DsQueryObject
	 */
	public function createDsqFromOid($oid, $type) {
		$dsQueryObj = new DsQueryObject($this->apiHelper);
		$dsQueryObj->setOid($oid);
		$dsQueryObj->setTable($type);

		return $dsQueryObj;
	}
}
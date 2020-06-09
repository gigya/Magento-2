<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\ds;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;

class DsQueryObject
{

	const VALUE_REG_EXP = '/.*(and|or|where)\s.*/i';

	/**
	 * @var string
	 */
	private $query;

	/**
	 * @var array
	 */
	private $fields;

	/**
	 * @var string
	 */
	private $table;

	/**
	 * @var array
	 */
	private $ors;

	/**
	 * @var array
	 */
	private $ands;

	/**
	 * @var string
	 */
	private $oid;

	/**
	 * @var array
	 */
	private $operators;

	/**
	 * @var string
	 */
	/**
	 * @var string
	 */
	private $uid = null;

	/**
	 * @var GigyaApiHelper
	 */
	private $apiHelper;

	/**
	 * DsQueryObject constructor.
	 *
	 * @param GigyaApiHelper $helper
	 *
	 */
	public function __construct($helper) {
		$this->apiHelper = $helper;
		$this->operators = [
			"<",
			">",
			"=",
			">=",
			"<=",
			"!=",
			"contains",
			"not contains",
		];
	}

	/**
	 * @param        $field
	 * @param        $op
	 * @param        $value
	 * @param string $valueType
	 *
	 * @return $this
	 * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\ds\DsQueryException
	 */
	public function addWhere($field, $op, $value, $valueType = "string") {
		$this->addAnd($field, $op, $value, $valueType);

		return $this;
	}

	/**
	 * @param string  $filed
	 * @param array   $terms
	 *
	 * @param  string $andOr
	 *
	 * @return $this
	 */
	public function addIn($filed, $terms, $andOr = "and") {

		array_walk(
			$terms, function(&$term) {
			$term = '"' . trim($term, '"') . '"';
		}
		);
		$ins = join(', ', $terms);
		$in  = " in($ins)";
		if ("or" == $andOr)
		{
			$this->ors[] = $this->prefixField($filed) . $in;
		}
		else
		{
			$this->ands[] = $this->prefixField($filed) . $in;
		}

		return $this;
	}

	/**
	 * @param string $field
	 * @param string $term
	 * @param string $andOr
	 *
	 * @return $this
	 * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\ds\DsQueryException
	 */
	public function addContains($field, $term, $andOr = "and") {
		if ("or" == $andOr)
		{
			$this->addOr($field, "contains", $term);
		}
		else
		{
			$this->addAnd($field, "contains", $term);
		}

		return $this;
	}

	/**
	 * @param string $field
	 * @param string $op
	 * @param string $value
	 * @param string $valueType
	 *
	 * @return $this
	 * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\ds\DsQueryException
	 */
	public function addOr($field, $op, $value, $valueType = "string") {
		$this->ors[] = $this->sanitizeAndBuild($field, $op, $value, $valueType);

		return $this;
	}

	/**
	 * @param        $field
	 * @param        $op
	 * @param        $value
	 * @param string $valueType
	 *
	 * @return string
	 * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\ds\DsQueryException
	 */
	private function sanitizeAndBuild($field, $op, $value, $valueType = "string") {
		if (preg_match(self::VALUE_REG_EXP, $value))
		{
			throw new \InvalidArgumentException("bad value string");
		}
		if ("string" == $valueType)
		{
			$value = '"' . trim($value, '"') . '"';
		}
		elseif ("bool" == $valueType)
		{
			$value = ($value) ? 'true' : 'false';
		}
		if (empty($field) || empty($op) || strlen(trim($value)) === 0)
		{
			throw new \InvalidArgumentException(
				"parameters can not be empty or a bad value string"
			);
		}
		if (!in_array($op, $this->operators))
		{
			throw new DsQueryException($op . " is not a valid operator");
		}

		return $this->prefixField(filter_var($field, FILTER_SANITIZE_STRING)) . " " . $op . " "
			   . $value;
	}

	/**
	 * @param string $field
	 * @param string $op
	 * @param string $value
	 * @param string $valueType
	 *
	 * @return $this
	 * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\ds\DsQueryException
	 */
	public function addAnd($field, $op, $value, $valueType = "string") {
		$this->ands[] = $this->sanitizeAndBuild($field, $op, $value, $valueType);

		return $this;
	}

	/**
	 * @param string $field
	 * @param string $term
	 * @param string $andOr
	 *
	 * @return $this
	 * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\ds\DsQueryException
	 */
	public function addNotContains($field, $term, $andOr = "and") {
		if ("or" == $andOr)
		{
			$this->addOr($field, "not contains", $term);
		}
		else
		{
			$this->addAnd($field, "not contains", $term);
		}

		return $this;
	}

	/**
	 * @param string $field
	 *
	 * @return $this
	 */
	public function addField($field) {
		$this->fields[] = $this->prefixField($field);

		return $this;
	}

	/**
	 * @param string $field
	 * @param string $andOr
	 *
	 * @return $this
	 */
	public function addIsNull($field, $andOr = "and") {
		if ("and" == strtolower($andOr))
		{
			$this->ands[] = $this->prefixField(trim($field)) . " is null";
		}
		elseif ("or" == strtolower($andOr))
		{
			$this->ors[] = $this->prefixField(trim($field)) . " is null";
		}
		else
		{
			throw new \InvalidArgumentException(
				'andOr parameter should "and" or "or"'
			);
		}

		return $this;

	}

	/**
	 * @param string $field
	 * @param string $andOr
	 *
	 * @return $this
	 */
	public function addIsNotNull($field, $andOr = "and") {
		if ("and" == strtolower($andOr))
		{
			$this->ands[] = $this->prefixField(trim($field)) . " is not null";
		}
		elseif ("or" == strtolower($andOr))
		{
			$this->ors[] = $this->prefixField(trim($field)) . " is not null";
		}
		else
		{
			throw new \InvalidArgumentException(
				'andOr parameter should "and" or "or"'
			);
		}

		return $this;

	}

	/**
	 * @return mixed
	 */
	public function getQuery() {
		return $this->query;
	}

	/**
	 * @param mixed $query
	 *
	 * @return $this
	 */
	public function setQuery($query) {
		$this->query = $query;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getFields() {
		return $this->fields;
	}


	// getters and setters

	/**
	 * @param mixed $fields
	 *
	 * @return $this
	 */
	public function setFields($fields) {
		$this->fields = $fields;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getTable() {
		return $this->table;
	}

	/**
	 * @param mixed $table
	 *
	 * @return $this
	 */
	public function setTable($table) {
		$this->table = $table;

		return $this;
	}

	/**
	 * @return string mixed
	 */
	public function getOid() {
		return $this->oid;
	}

	/**
	 * @param string $oid
	 *
	 * @return $this
	 */
	public function setOid($oid) {
		$this->oid = $oid;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getUid() {
		return $this->uid;
	}

	/**
	 * @param string $uid
	 */
	public function setUid($uid) {
		$this->uid = $uid;
	}

	/**
	 * @return \Gigya\PHP\GSObject
	 * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException
	 */
	public function dsGet() {
		$paramsArray = ["oid" => $this->oid, "type" => $this->table];
		if (!empty($this->uid))
		{
			$paramsArray['UID'] = $this->uid;
		}
		if (count($this->fields) > 0)
		{
			$paramsArray['fields'] = $this->buildFieldsStringForGet();
		}
		$res = $this->apiHelper->sendApiCall("ds.get", $paramsArray);

		return $res->getData();
	}

	private function buildFieldsString() {
		if (in_array("*", $this->fields))
		{
			return '*';
		}
		$queryFields = [];
		foreach ($this->fields as $field)
		{
			$queryFields[] = $this->prefixField($field);
		}

		return join(", ", $queryFields);
	}

	private function prefixField($field) {
		$no_prefix_fields = ['uid', 'oid'];
		if (in_array(strtolower($field), $no_prefix_fields))
		{
			return $field;
		}

		return 'data.' . $field;
	}

	/**
	 * @return \Gigya\PHP\GSObject
	 * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\ds\DsQueryException
	 * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException
	 */
	public function dsSearch() {
		if (empty($this->query))
		{
			$this->buildQuery();
		}
		$res = $this->apiHelper->sendApiCall(
			"ds.search", ["query" => $this->query]
		);

		return $res->getData();
	}


	private function buildFieldsStringForGet() {
		return in_array("*", $this->fields)
			? "*"
			: join(
				",", $this->fields
			);

	}

	/**
	 * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\ds\DsQueryException
	 */
	protected function buildQuery() {
		if (!$this->checkAllRequired())
		{
			throw new DsQueryException("missing fields or table");
		}
		$fields = $this->buildFieldsString();
		$q      = "SELECT " . $fields . " FROM " . $this->table;
		$where  = true;
		if (!empty($this->ands))
		{
			if ($where)
			{
				$q     .= " WHERE ";
				$where = false;
			}
			else
			{
				$q .= " AND ";
			}
			$ands = join(" AND ", $this->ands);
			$q    .= $ands;
		}
		if (!empty($this->ors))
		{
			if ($where)
			{
				$q .= " WHERE ";
			}
			else
			{
				$q .= " OR ";
			}
			$ors = join(" OR ", $this->ors);
			$q   .= $ors;
		}
		$this->query = $q;
	}

	/**
	 * @return bool
	 */
	private function checkAllRequired() {
		return !(empty($this->fields) && empty($this->table));
	}

	/**
	 * @throws \Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException
	 */
	public function dsDelete() {
		$paramsArray = ["oid" => $this->oid, "type" => $this->table];
		if (!empty($this->uid))
		{
			$paramsArray['UID'] = $this->uid;
		}
		if (count($this->fields) > 0)
		{
			$paramsArray['fields'] = $this->buildFieldsString();
		}
		$this->apiHelper->sendApiCall("ds.delete", $paramsArray);
	}
}
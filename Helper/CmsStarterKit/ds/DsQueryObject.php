<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\ds;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;
use Gigya\GigyaIM\Helper\CmsStarterKit\GSApiException;
use Gigya\PHP\GSException;
use Gigya\PHP\GSObject;

class DsQueryObject
{

    const VALUE_REG_EXP = '/.*(and|or|where)\s.*/i';

    /**
     * @var string
     */
    private string $query;

    /**
     * @var array
     */
    private array $fields;

    /**
     * @var string
     */
    private string $table;

    /**
     * @var array
     */
    private array $ors;

    /**
     * @var array
     */
    private array $ands;

    /**
     * @var string
     */
    private string $oid;

    /**
     * @var array
     */
    private array $operators;

    /**
     * @var string|null
     */
    private ?string $uid = null;

    /**
     * @var GigyaApiHelper
     */
    private GigyaApiHelper $apiHelper;

    /**
     * DsQueryObject constructor.
     *
     * @param GigyaApiHelper $helper
     *
     */
    public function __construct($helper)
    {
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
     * @throws DsQueryException
     */
    public function addWhere($field, $op, $value, string $valueType = "string"): static
    {
        $this->addAnd($field, $op, $value, $valueType);

        return $this;
    }

    /**
     * @param string $filed
     * @param array $terms
     *
     * @param string $andOr
     *
     * @return $this
     */
    public function addIn(string $filed, array $terms, string $andOr = "and"): static
    {

        array_walk(
            $terms,
            function (&$term) {
                $term = '"' . trim($term, '"') . '"';
            }
        );
        $ins = join(', ', $terms);
        $in  = " in($ins)";
        if ("or" == $andOr) {
            $this->ors[] = $this->prefixField($filed) . $in;
        } else {
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
     * @throws DsQueryException
     */
    public function addContains(string $field, string $term, string $andOr = "and"): static
    {
        if ("or" == $andOr) {
            $this->addOr($field, "contains", $term);
        } else {
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
     * @throws DsQueryException
     */
    public function addOr(string $field, string $op, string $value, string $valueType = "string"): static
    {
        $this->ors[] = $this->sanitizeAndBuild($field, $op, $value, $valueType);

        return $this;
    }

    /**
     * @param string $field
     * @param string $op
     * @param string $value
     * @param string $valueType
     *
     * @return string
     * @throws DsQueryException
     */
    private function sanitizeAndBuild(string $field, string $op, string $value, string $valueType = "string"): string
    {
        if (preg_match(self::VALUE_REG_EXP, $value)) {
            throw new \InvalidArgumentException("bad value string");
        }

        if ("string" == $valueType) {
            $value = '"' . trim($value, '"') . '"';
        } elseif ("bool" == $valueType) {
            $value = ($value) ? 'true' : 'false';
        }

        if (empty($field) || empty($op) || strlen(trim($value)) === 0) {
            throw new \InvalidArgumentException(
                "parameters can not be empty or a bad value string"
            );
        }

        if (!in_array($op, $this->operators)) {
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
     * @throws DsQueryException
     */
    public function addAnd(string $field, string $op, string $value, string $valueType = "string"): static
    {
        $this->ands[] = $this->sanitizeAndBuild($field, $op, $value, $valueType);

        return $this;
    }

    /**
     * @param string $field
     * @param string $term
     * @param string $andOr
     *
     * @return $this
     * @throws DsQueryException
     */
    public function addNotContains(string $field, string $term, string $andOr = "and"): static
    {
        if ("or" == $andOr) {
            $this->addOr($field, "not contains", $term);
        } else {
            $this->addAnd($field, "not contains", $term);
        }

        return $this;
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    public function addField(string $field): static
    {
        $this->fields[] = $this->prefixField($field);

        return $this;
    }

    /**
     * @param string $field
     * @param string $andOr
     *
     * @return $this
     */
    public function addIsNull(string $field, string $andOr = "and"): static
    {
        if ("and" == strtolower($andOr)) {
            $this->ands[] = $this->prefixField(trim($field)) . " is null";
        } elseif ("or" == strtolower($andOr)) {
            $this->ors[] = $this->prefixField(trim($field)) . " is null";
        } else {
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
    public function addIsNotNull(string $field, string $andOr = "and"): static
    {
        if ("and" == strtolower($andOr)) {
            $this->ands[] = $this->prefixField(trim($field)) . " is not null";
        } elseif ("or" == strtolower($andOr)) {
            $this->ors[] = $this->prefixField(trim($field)) . " is not null";
        } else {
            throw new \InvalidArgumentException(
                'andOr parameter should "and" or "or"'
            );
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getQuery(): mixed
    {
        return $this->query;
    }

    /**
     * @param mixed $query
     *
     * @return $this
     */
    public function setQuery(mixed $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFields(): mixed
    {
        return $this->fields;
    }


    // getters and setters

    /**
     * @param mixed $fields
     *
     * @return $this
     */
    public function setFields(mixed $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTable(): mixed
    {
        return $this->table;
    }

    /**
     * @param mixed $table
     *
     * @return $this
     */
    public function setTable($table): static
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @return string mixed
     */
    public function getOid(): string
    {
        return $this->oid;
    }

    /**
     * @param string $oid
     *
     * @return $this
     */
    public function setOid($oid): static
    {
        $this->oid = $oid;

        return $this;
    }

    /**
     * @return string
     */
    public function getUid(): ?string
    {
        return $this->uid;
    }

    /**
     * @param string $uid
     */
    public function setUid($uid): void
    {
        $this->uid = $uid;
    }

    /**
     * @return GSObject
     * @throws GSApiException
     * @throws GSException
     */
    public function dsGet(): GSObject
    {
        $paramsArray = ["oid" => $this->oid, "type" => $this->table];
        if (!empty($this->uid)) {
            $paramsArray['UID'] = $this->uid;
        }

        if (count($this->fields) > 0) {
            $paramsArray['fields'] = $this->buildFieldsStringForGet();
        }

        $res = $this->apiHelper->sendApiCall("ds.get", $paramsArray);

        return $res->getData();
    }

    private function buildFieldsString(): string
    {
        if (in_array("*", $this->fields)) {
            return '*';
        }

        $queryFields = [];
        foreach ($this->fields as $field) {
            $queryFields[] = $this->prefixField($field);
        }

        return join(", ", $queryFields);
    }

    private function prefixField($field)
    {
        $no_prefix_fields = ['uid', 'oid'];
        if (in_array(strtolower($field), $no_prefix_fields)) {
            return $field;
        }

        return 'data.' . $field;
    }

    /**
     * @return GSObject
     * @throws DsQueryException
     * @throws GSApiException
     * @throws GSException
     */
    public function dsSearch(): GSObject
    {
        if (empty($this->query)) {
            $this->buildQuery();
        }

        $res = $this->apiHelper->sendApiCall(
            "ds.search",
            ["query" => $this->query]
        );

        return $res->getData();
    }


    private function buildFieldsStringForGet(): string
    {
        return in_array("*", $this->fields)
            ? "*"
            : join(
                ",",
                $this->fields
            );
    }

    /**
     * @throws DsQueryException
     */
    protected function buildQuery(): void
    {
        if (!$this->checkAllRequired()) {
            throw new DsQueryException("missing fields or table");
        }

        $fields = $this->buildFieldsString();
        $q      = "SELECT " . $fields . " FROM " . $this->table;
        $where  = true;
        if (!empty($this->ands)) {
            if ($where) {
                $q     .= " WHERE ";
                $where = false;
            } else {
                $q .= " AND ";
            }

            $ands = join(" AND ", $this->ands);
            $q    .= $ands;
        }

        if (!empty($this->ors)) {
            if ($where) {
                $q .= " WHERE ";
            } else {
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
    private function checkAllRequired(): bool
    {
        return !(empty($this->fields) && empty($this->table));
    }

    /**
     * @throws GSApiException
     * @throws GSException
     */
    public function dsDelete(): void
    {
        $paramsArray = ["oid" => $this->oid, "type" => $this->table];
        if (!empty($this->uid)) {
            $paramsArray['UID'] = $this->uid;
        }

        if (count($this->fields) > 0) {
            $paramsArray['fields'] = $this->buildFieldsString();
        }

        $this->apiHelper->sendApiCall("ds.delete", $paramsArray);
    }
}

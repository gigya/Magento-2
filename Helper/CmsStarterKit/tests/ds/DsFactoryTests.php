<?php

use Gigya\GigyaIM\Helper\CmsStarterKit\ds\DsFactory;
use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;
use PHPUnit\Framework\TestCase;

class DsFactoryTests extends TestCase
{
	/**
	 * @var DsFactory
	 */
	private $factory;

	public function testStringQuery() {
		$qry      = "SELECT * FROM test";
		$queryObj = $this->factory->createDsqFromQuery($qry);
		$this->assertEquals($qry, $queryObj->getQuery(), "Testing query strings");
	}

	public function testFromFields() {
		$fields   = array("test", "foo", "bar", "baz");
		$type     = "example";
		$queryObj = $this->factory->createDsqFromFields($type, $fields);
		$build    = self::getMethod('buildQuery');
		$build->invoke($queryObj);
		$qry         = $queryObj->getQuery();
		$expectedQry = "SELECT test, foo, bar, baz FROM example";
		$this->assertEquals($expectedQry, $qry);

	}

	public function testFromOid() {
		$queryObj = $this->factory->createDsqFromOid("testOid", "test");
		$this->assertEquals("testOid", $queryObj->getOid());
		$this->assertEquals("test", $queryObj->getTable());
		$this->assertEmpty($queryObj->getQuery());
	}

	public function testFromWhere() {
		$fields   = array("test", "foo", "bar", "baz");
		$type     = "example";
		$queryObj = $this->factory->createDsqFromWhere($type, $fields, "foo", ">", 1, "int");
		$build    = self::getMethod('buildQuery');
		$build->invoke($queryObj);
		$qry         = $queryObj->getQuery();
		$expectedQry = "SELECT test, foo, bar, baz FROM example WHERE foo > 1";
		$this->assertEquals($expectedQry, $qry);

	}

	protected function setUp() {
		$apiHelper     = new GigyaApiHelper("apiKey", "appKey", "appSecret", "us1.gigya.com");
		$this->factory = new DsFactory($apiHelper);
	}

	protected static function getMethod($name) {
		$class  = new ReflectionClass('Gigya\ds\DsQueryObject');
		$method = $class->getMethod($name);
		$method->setAccessible(true);

		return $method;
	}
}

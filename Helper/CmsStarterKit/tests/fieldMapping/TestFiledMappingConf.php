<?php
/**
 * Created by PhpStorm.
 * User: Yaniv Aran-Shamir
 * Date: 7/17/16
 * Time: 1:58 PM
 */

namespace Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping;


class TestFiledMappingConf extends \PHPUnit_Framework_TestCase
{


    /**
     * @var Conf
     */
    private $conf;

    public function testGigyaKeyed()
    {
        $gigyaKeyed = $this->conf->getGigyaKeyed();
        $this->assertArrayHasKey("profile.gender", $gigyaKeyed);
        $this->assertArrayHasKey("data.test", $gigyaKeyed);
        $this->assertArrayHasKey("ds.table1.test", $gigyaKeyed);
    }

    public function testCmsKeyed()
    {
        $cmsKeyed = $this->conf->getCmsKeyed();
        $this->assertArrayHasKey("gender", $cmsKeyed);
        $this->assertArrayHasKey("custom2", $cmsKeyed);
    }

    public function testConfItem()
    {
        $gigyaKeyed = $this->conf->getGigyaKeyed();
        $this->assertCount(1, $gigyaKeyed['ds.table1.test'], "Test only one mapping for this field");
        /**
         * @var ConfItem $confItem
         */
        $confItem = $gigyaKeyed['ds.table1.test'][0];
        $this->assertEquals("someField", $confItem->getCmsName(), "Tests cms name");
        $this->assertEquals("ds.table1.test", $confItem->getGigyaName(), "Test gigya name");
        $this->assertEquals("string", $confItem->getCmsType(), "Test cms type");
        $this->assertEquals("string", $confItem->getGigyaType(), "Test gigya type");
        $expectedArray = array("oid" => "segments");
        $this->assertEquals($expectedArray, $confItem->getCustom(), "test custom configuration");
    }


    protected function setUp()
    {
        $json       = file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR
            . "fieldMapping.json"
        );
        $this->conf = new Conf($json);
    }


}

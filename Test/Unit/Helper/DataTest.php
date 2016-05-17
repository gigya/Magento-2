<?php
/**
 * Created by PhpStorm.
 * User: guy.av
 * Date: 16/05/2016
 * Time: 10:11
 */
namespace Gigya\GigyaIM\Test\Unit\Helper;

class DataTest extends \PHPUnit_Framework_TestCase {

    protected $data;

    protected function setUp () {
        $objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $this->data = $objectManager->getObject('Gigya\GigyaIM\Helper\Data');
    }

    public function testSomeFunction() {
        $this->assertEquals(1,1, "First Test Report is false");
    }

    protected function tearDown()
    {
        $this->data = null;
    }

}
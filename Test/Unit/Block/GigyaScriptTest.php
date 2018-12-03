<?php
/**
 * Test th general Gigya Script load block.
 * loads Gigya script on each page load, with various parameters
 */
namespace Gigya\GigyaIM\Test\Unit\Block;

use Gigya\GigyaIM\Block;
use PHPUnit\Framework\TestCase;

class GigyaScriptTest extends TestCase
{

    /**
     * @var Gigya\GigyaIM\Block\GigyaScript
     */
    protected $block;
    
    protected $scopeConfig;

    /**
     * Mock scopeConfig to tests with different System configuration values
     */
    protected function setUp()
    {
        // when creating mock from interface, make sure to implement all interface methods
        $this->scopeConfig = $this->getMockBuilder(
            '\Magento\Framework\App\Config\ScopeConfigInterface'
        )->setMethods(
            ['isSetFlag', 'getValue']
        )->getMock();

        // create the tested object, with the mocked config class
        $objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $this->block = $objectManager->getObject(
            'Gigya\GigyaIM\Block\GigyaScript',
            ["scopeConfig" => $this->scopeConfig]
        );
    }

    /**
     * Test language settings:
     * Normal behavior - language is set to "en_US" -> getLanguage returns "en_US"
     */
    public function testLanguage_gigyaLanguageIsSetToEn() {
        $this->scopeConfig->expects($this->any())->method('getValue')
            ->with("gigya_section/general/language")
            ->will($this->returnValue('en'));

        $this->assertEquals('en', $this->block->getLanguage(),
            "language is set to \"en\", but getLanguage does not return the same value");
    }

    protected function tearDown()
    {
        $this->block = null;
        $this->scopeConfig = null;
    }

}

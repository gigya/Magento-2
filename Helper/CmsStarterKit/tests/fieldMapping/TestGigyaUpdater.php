<?php
/**
 * Created by PhpStorm.
 * User: Yaniv Aran-Shamir
 * Date: 7/18/16
 * Time: 9:06 AM
 */

namespace Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;
use PHPUnit\Framework\TestCase;

class TestGigyaUpdater extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject $helper
     */
    private $helper;

    public function testUpdateGigya()
    {
        $expectedData = array(
            'profile' => array(
                'gender' => 'f'
            ),
            'data' => array(
                'test1' => 'some value',
                'test' => array('deep' => array('deep' => array('deep' => 'deep value')))
            )
        );
        $uid = '12345';

        $this->helper->expects($this->once())->method('updateGigyaAccount')->with($uid, $expectedData);

        $cmsArray = array(
            'custom2' => 'some value',
            'gender' => 'f',
            'custom3' => 'deep value'
        );
        $path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'fieldMapping.json';

        /** @var \Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping\GigyaUpdater $updater */
        $updater = $this->getMockBuilder(GigyaUpdater::class)
            ->setConstructorArgs(array($cmsArray, $uid, $path, $this->helper))
            ->setMethods(array('callCmsHook', 'setMappingCache', 'getMappingFromCache'))
            ->getMock();
        $updater->method('getMappingFromCache')->willReturn(false);
        $this->assertTrue($updater->isMapped());
        $updater->updateGigya();
    }


    protected function setUp()
    {
        $this->helper = $this->getMockBuilder(GigyaApiHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['updateGigyaAccount'])
            ->getMock();

    }


}

<?php 

use PHPUnit\Framework\TestCase;

class GigyaInfoTest extends TestCase
{
    protected $gigyaInfo;

    protected function setUp(): void
    {
        $this->gigyaInfo = new GigyaInfo();
    }

    public function testGetProfileDesktopScreensetId(): void
    {
        // Test when the config model returns a valid desktop screenset ID
        $configModelMock = $this->createMock(ConfigModel::class);
        $configModelMock->expects($this->once())
            ->method('getProfileDesktopScreensetId')
            ->willReturn('desktop_screenset_id');
        $this->gigyaInfo->setConfigModel($configModelMock);
        $this->assertEquals('desktop_screenset_id', $this->gigyaInfo->getProfileDesktopScreensetId());

        // Test when the config model returns an empty desktop screenset ID
        $configModelMock = $this->createMock(ConfigModel::class);
        $configModelMock->expects($this->once())
            ->method('getProfileDesktopScreensetId')
            ->willReturn('');
        $this->gigyaInfo->setConfigModel($configModelMock);
        $this->assertEquals('', $this->gigyaInfo->getProfileDesktopScreensetId());
    }

    public function testGetProfileMobileScreensetId(): void
    {
        // Test when the config model returns a valid mobile screenset ID
        $configModelMock = $this->createMock(ConfigModel::class);
        $configModelMock->expects($this->once())
            ->method('getProfileMobileScreensetId')
            ->willReturn('mobile_screenset_id');
        $this->gigyaInfo->setConfigModel($configModelMock);
        $this->assertEquals('mobile_screenset_id', $this->gigyaInfo->getProfileMobileScreensetId());

        // Test when the config model returns an empty mobile screenset ID
        $configModelMock = $this->createMock(ConfigModel::class);
        $configModelMock->expects($this->once())
            ->method('getProfileMobileScreensetId')
            ->willReturn('');
        $this->gigyaInfo->setConfigModel($configModelMock);
        $this->assertEquals('', $this->gigyaInfo->getProfileMobileScreensetId());
    }
}
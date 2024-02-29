<?php 

use PHPUnit\Framework\TestCase;

class GigyaAuthenticationPopupTest extends TestCase
{
    protected $block;

    protected function setUp(): void
    {
        $this->block = new GigyaAuthenticationPopup();
    }

    public function testGetLoginDesktopScreensetId(): void
    {
        $expected = 'desktop_screenset_id';
        $this->block->config = $this->createMock(Config::class);
        $this->block->config->method('getLoginDesktopScreensetId')->willReturn($expected);

        $actual = $this->block->getLoginDesktopScreensetId();

        $this->assertSame($expected, $actual);
    }

    public function testGetLoginMobileScreensetId(): void
    {
        $expected = 'mobile_screenset_id';
        $this->block->config = $this->createMock(Config::class);
        $this->block->config->method('getLoginMobileScreensetId')->willReturn($expected);

        $actual = $this->block->getLoginMobileScreensetId();

        $this->assertSame($expected, $actual);
    }

    public function testToHtmlWhenGigyaEnabled(): void
    {
        $this->block->config = $this->createMock(Config::class);
        $this->block->config->method('isGigyaEnabled')->willReturn(true);

        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->once())->method('unsetElement')->withConsecutive(['customer_form_register'], ['customer_edit']);
        $this->block->setLayout($layout);

        $expected = 'parent_html';
        $this->block->expects($this->once())->method('parent::_toHtml')->willReturn($expected);

        $actual = $this->block->_toHtml();

        $this->assertSame($expected, $actual);
    }

    public function testToHtmlWhenGigyaDisabled(): void
    {
        $this->block->config = $this->createMock(Config::class);
        $this->block->config->method('isGigyaEnabled')->willReturn(false);

        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects($this->never())->method('unsetElement');
        $this->block->setLayout($layout);

        $expected = 'parent_html';
        $this->block->expects($this->once())->method('parent::_toHtml')->willReturn($expected);

        $actual = $this->block->_toHtml();

        $this->assertSame($expected, $actual);
    }
}
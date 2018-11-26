<?php
include_once '../GigyaApiHelper.php';

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;
use PHPUnit\Framework\TestCase;

class TestEncryption extends TestCase
{
	private $key;

	public function testEnc() {
		$toEnc  = "testing testing 123";
		$encStr = GigyaApiHelper::enc($toEnc, $this->key);
		$decStr = GigyaApiHelper::decrypt($encStr, $this->key);
		$this->assertEquals($toEnc, trim($decStr));
	}

	protected function setUp() {
		$this->key = GigyaApiHelper::genKeyFromString("testGenKey");
	}
}

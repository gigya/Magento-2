<?php
	/* Requires setting include path to the package's src directory */
	include_once 'GigyaApiHelper.php';

	use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaApiHelper;

	/**
	 * Created by PhpStorm.
	 * User: Yaniv Aran-Shamir
	 * Date: 4/7/16
	 * Time: 8:47 PM
	 */
	class TestEncryption extends PHPUnit_Framework_TestCase
	{
		private $key;

		public function testEnc() {
			$toEnc = "testing testing 123";
			$encStr = GigyaApiHelper::enc($toEnc, $this->key);
			$decStr = GigyaApiHelper::decrypt($encStr, $this->key);
			$this->assertEquals($toEnc, trim($decStr));
		}

		protected function setUp() {
			$this->key = GigyaApiHelper::genKeyFromString("testGenKey");
		}
	}

<?php

namespace Gigya\GigyaIM\Test\Unit\Encryption;

use PHPUnit\Framework\TestCase;

class EncryptorTest extends TestCase
{
    protected $objectManager;

    public function setUp(): void
    {
        parent::setUp();
        // create the tested object, with the mocked config class
        $this->objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
    }

    public function testSetUseGigyaEncryptor()
    {
        $encryptor = $this->objectManager->getObject(
            'Gigya\GigyaIM\Encryption\Encryptor',
            []
        );

        $encryptor->setUseGigyaEncryptor(true);
        $this->assertTrue($encryptor->getUseGigyaEncryptor());
    }

    public function testEncryptWithGigya()
    {
        $encryptor = $this->objectManager->getObject(
            'Gigya\GigyaIM\Encryption\Encryptor',
            []
        );

        $encryptor->setUseGigyaEncryptor(true);
        $encryptedString = 'test';
        $decryptedString = 'test';
        $encrypted = $encryptor->encrypt($decryptedString);
        $this->assertEquals($encryptedString, $encrypted);
    }

    public function testDecryptWithGigya()
    {
        $encryptor = $this->objectManager->getObject(
            'Gigya\GigyaIM\Encryption\Encryptor',
            []
        );

        $encryptor->setUseGigyaEncryptor(true);
        $encryptedString = 'test';
        $decryptedString = 'test';
        $decrypted = $encryptor->decrypt($encryptedString);

        $this->assertEquals($decryptedString, $decrypted);
    }

    public function testEncryptMagento()
    {
        $encryptor = $this->objectManager->getObject(
            'Gigya\GigyaIM\Encryption\Encryptor',
            []
        );

        $encryptor->setUseGigyaEncryptor(false);
        $encryptedString = 'test';
        $decryptedString = 'test';
        $encrypted = $encryptor->encrypt($decryptedString);
        $this->assertEquals($encryptedString, $encrypted);
    }

    public function testDecryptMagento()
    {
        $encryptor = $this->objectManager->getObject(
            'Gigya\GigyaIM\Encryption\Encryptor',
            []
        );

        $encryptor->setUseGigyaEncryptor(false);
        $encryptedString = 'test';
        $decryptedString = 'test';
        $decrypted = $encryptor->decrypt($encryptedString);

        $this->assertEquals($decryptedString, $decrypted);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }
}

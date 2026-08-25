<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Security\PiiCipherService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\EmailPii;
use RuntimeException;

/**
 * ADR-008 PiiCipherService contracts (Phase 9 / Task 9.1B).
 *
 * @internal
 */
final class PiiCipherServiceTest extends CIUnitTestCase
{
    private PiiCipherService $cipher;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new EmailPii();
        $config->encryptionKeyHex = 'e49df5a504b3e8f1ca7dff30c57a4f7fa75d75780e748ec3c093bcd288e9383e';
        $config->lookupHmacKeyHex = 'be3a78d9d3dc3a874f32ce45f215316506e7c7cc98d99fc95a29ff3f9dfbd752';

        $this->cipher = new PiiCipherService($config);
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plain = 'User@Example.COM';
        $cipher = $this->cipher->encrypt($plain);
        $this->assertNotSame($plain, $cipher);
        $this->assertSame('user@example.com', $this->cipher->decrypt($cipher));
    }

    public function testLookupHashIsCaseInsensitiveAndDeterministic(): void
    {
        $a = $this->cipher->getLookupHash('Admin@Example.com');
        $b = $this->cipher->getLookupHash('admin@example.com');
        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a);
    }

    public function testEncryptProducesDistinctCiphertextsForSameEmail(): void
    {
        $one = $this->cipher->encrypt('same@example.com');
        $two = $this->cipher->encrypt('same@example.com');
        $this->assertNotSame($one, $two);
        $this->assertSame(
            $this->cipher->decrypt($one),
            $this->cipher->decrypt($two),
        );
    }

    public function testCiphertextIsNotLookupHash(): void
    {
        $email = 'separate@example.com';
        $cipher = $this->cipher->encrypt($email);
        $hash   = $this->cipher->getLookupHash($email);
        $this->assertNotSame($cipher, $hash);
        $this->assertStringNotContainsString('separate@example.com', $cipher);
        $this->assertStringNotContainsString('separate@example.com', $hash);
    }

    public function testIdenticalKeysAreRejected(): void
    {
        $config = new EmailPii();
        $config->encryptionKeyHex = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $config->lookupHmacKeyHex = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must differ');
        new PiiCipherService($config);
    }

    public function testInvalidKeyLengthIsRejectedWithoutEchoingSecret(): void
    {
        $config = new EmailPii();
        $config->encryptionKeyHex = 'short';
        $config->lookupHmacKeyHex = 'be3a78d9d3dc3a874f32ce45f215316506e7c7cc98d99fc95a29ff3f9dfbd752';

        try {
            new PiiCipherService($config);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('EMAIL_ENCRYPTION_KEY', $e->getMessage());
            $this->assertStringNotContainsString('short', $e->getMessage());
        }
    }
}

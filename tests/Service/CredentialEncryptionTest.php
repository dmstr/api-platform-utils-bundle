<?php
// file generated with AI assistance: Claude Code - 2026-06-13 23:14:54 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Service;

use Dmstr\ApiPlatformUtils\Service\CredentialEncryption;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see CredentialEncryption}.
 *
 * Security-critical: verifies the XChaCha20-Poly1305 round-trip, key
 * validation, nonce randomisation and authenticated-decryption failure modes
 * without any framework or filesystem dependency.
 */
final class CredentialEncryptionTest extends TestCase
{
    /** A deterministic 32-byte raw key for reproducible tests. */
    private const KEY = '0123456789abcdef0123456789abcdef';

    public function testEncryptDecryptRoundTrip(): void
    {
        $service = new CredentialEncryption(self::KEY);
        $credentials = ['username' => 'alice', 'password' => 's3cr3t!', 'scopes' => ['read', 'write']];

        $cipher = $service->encrypt($credentials);
        self::assertNotSame('', $cipher);
        self::assertSame($credentials, $service->decrypt($cipher));
    }

    public function testGenerateKeyProducesUsable32ByteKey(): void
    {
        $encoded = CredentialEncryption::generateKey();
        $raw = base64_decode($encoded, true);

        self::assertNotFalse($raw, 'generateKey() must return valid base64');
        self::assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, \strlen($raw));

        // A freshly generated key must work for a real round-trip.
        $service = new CredentialEncryption($raw);
        self::assertSame(['a' => 1], $service->decrypt($service->encrypt(['a' => 1])));
    }

    public function testConstructorRejectsWrongKeyLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CredentialEncryption('too-short');
    }

    public function testDecryptRejectsInvalidBase64(): void
    {
        $service = new CredentialEncryption(self::KEY);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid base64 encoding');
        $service->decrypt('###-not-base64-###');
    }

    public function testDecryptRejectsTamperedCiphertext(): void
    {
        $service = new CredentialEncryption(self::KEY);
        $cipher = $service->encrypt(['token' => 'abc']);

        // Flip the final byte of the authenticated ciphertext.
        $raw = base64_decode($cipher, true);
        $raw[\strlen($raw) - 1] = $raw[\strlen($raw) - 1] === "\x00" ? "\x01" : "\x00";
        $tampered = base64_encode($raw);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        $service->decrypt($tampered);
    }

    public function testDecryptWithWrongKeyFails(): void
    {
        $cipher = (new CredentialEncryption(self::KEY))->encrypt(['token' => 'abc']);

        $otherKey = 'fedcba9876543210fedcba9876543210';
        $this->expectException(\RuntimeException::class);
        (new CredentialEncryption($otherKey))->decrypt($cipher);
    }

    public function testEncryptIsNonDeterministicPerNonce(): void
    {
        $service = new CredentialEncryption(self::KEY);
        $payload = ['username' => 'bob'];

        self::assertNotSame(
            $service->encrypt($payload),
            $service->encrypt($payload),
            'Each encryption must use a fresh random nonce.'
        );
    }
}

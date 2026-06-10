<?php
// file generated with AI assistance: Claude Code - 2025-11-22

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Service;

/**
 * Service for encrypting/decrypting API credentials
 *
 * Uses libsodium for encryption (built into PHP 7.2+)
 * Algorithm: XChaCha20-Poly1305 (via crypto_secretbox)
 */
class CredentialEncryption
{
    private readonly string $key;

    public function __construct(string $encryptionKey)
    {
        // Ensure key is properly formatted (32 bytes for XChaCha20-Poly1305)
        if (strlen($encryptionKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Encryption key must be exactly %d bytes, got %d',
                    SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
                    strlen($encryptionKey)
                )
            );
        }

        $this->key = $encryptionKey;
    }

    /**
     * Encrypt credentials array
     *
     * @param array $credentials Plain credentials
     * @return string Base64-encoded encrypted data
     */
    public function encrypt(array $credentials): string
    {
        $json = json_encode($credentials, JSON_THROW_ON_ERROR);

        // Generate a nonce (number used once)
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Encrypt the data
        $ciphertext = sodium_crypto_secretbox($json, $nonce, $this->key);

        // Combine nonce and ciphertext
        $encrypted = $nonce . $ciphertext;

        // Clean up sensitive data from memory
        sodium_memzero($json);

        return base64_encode($encrypted);
    }

    /**
     * Decrypt credentials
     *
     * @param string $encryptedData Base64-encoded encrypted data
     * @return array Decrypted credentials
     * @throws \RuntimeException If decryption fails
     */
    public function decrypt(string $encryptedData): array
    {
        $encrypted = base64_decode($encryptedData, true);

        if ($encrypted === false) {
            throw new \RuntimeException('Invalid base64 encoding');
        }

        // Extract nonce and ciphertext
        $nonce = mb_substr($encrypted, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($encrypted, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        // Decrypt
        $json = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($json === false) {
            throw new \RuntimeException('Decryption failed - invalid key or corrupted data');
        }

        $credentials = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // Clean up sensitive data from memory
        sodium_memzero($json);

        return $credentials;
    }

    /**
     * Generate a new encryption key
     *
     * This should be called once during setup and the key stored securely
     *
     * @return string Base64-encoded key
     */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }
}

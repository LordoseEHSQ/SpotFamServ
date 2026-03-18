<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Security;

use App\Module\Spotify\Application\Port\TokenEncryptionInterface;

/**
 * Encrypts/decrypts tokens at rest using XChaCha20-Poly1305 (sodium).
 * Key is derived from APP_SECRET (32 bytes via generic hash).
 */
final class TokenEncryptionService implements TokenEncryptionInterface
{
    private const NONCE_SIZE = 24; // SODIUM_CRYPTO_SECRETBOX_NPUBBYTES = 24

    public function __construct(
        #[\SensitiveParameter]
        string $appSecret,
    ) {
        $this->encryptionKey = sodium_crypto_generichash($appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private readonly string $encryptionKey;

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(self::NONCE_SIZE);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $this->encryptionKey);
        return base64_encode($nonce . $cipher);
    }

    public function decrypt(string $cipher): string
    {
        $raw = base64_decode($cipher, true);
        if ($raw === false || strlen($raw) < self::NONCE_SIZE) {
            throw new \RuntimeException('Invalid encrypted token format.');
        }
        $nonce = substr($raw, 0, self::NONCE_SIZE);
        $box = substr($raw, self::NONCE_SIZE);
        $decrypted = sodium_crypto_secretbox_open($box, $nonce, $this->encryptionKey);
        if ($decrypted === false) {
            throw new \RuntimeException('Token decryption failed.');
        }
        return $decrypted;
    }
}

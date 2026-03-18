<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Port;

/**
 * Encrypt/decrypt tokens at rest. Used by repository or listener before DB write / after DB read.
 */
interface TokenEncryptionInterface
{
    public function encrypt(string $plain): string;

    public function decrypt(string $cipher): string;
}

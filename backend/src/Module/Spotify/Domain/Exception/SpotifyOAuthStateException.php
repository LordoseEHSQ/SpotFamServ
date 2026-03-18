<?php

declare(strict_types=1);

namespace App\Module\Spotify\Domain\Exception;

/**
 * Invalid or expired OAuth state parameter.
 */
final class SpotifyOAuthStateException extends SpotifyDomainException
{
    public function __construct(string $message = 'Invalid or expired OAuth state.')
    {
        parent::__construct($message);
    }
}

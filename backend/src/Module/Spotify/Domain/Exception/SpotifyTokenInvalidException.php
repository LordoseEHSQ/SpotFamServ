<?php

declare(strict_types=1);

namespace App\Module\Spotify\Domain\Exception;

/**
 * Spotify access token is invalid or expired and cannot be refreshed.
 */
final class SpotifyTokenInvalidException extends SpotifyDomainException
{
    public function __construct(string $message = 'Spotify token invalid or expired.')
    {
        parent::__construct($message);
    }
}

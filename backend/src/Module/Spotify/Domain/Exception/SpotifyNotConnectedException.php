<?php

declare(strict_types=1);

namespace App\Module\Spotify\Domain\Exception;

/**
 * Profile has no Spotify account link (not connected).
 */
final class SpotifyNotConnectedException extends SpotifyDomainException
{
    public function __construct(string $message = 'Spotify account not connected for this profile.')
    {
        parent::__construct($message);
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Spotify\Domain\Exception;

/**
 * No Spotify playback device available or configured.
 */
final class SpotifyNoDeviceException extends SpotifyDomainException
{
    public function __construct(string $message = 'No Spotify playback device available.')
    {
        parent::__construct($message);
    }
}

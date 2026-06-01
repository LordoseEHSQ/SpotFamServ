<?php

declare(strict_types=1);

namespace App\Module\Spotify\Domain\Exception;

/**
 * Raised when an operation targets a Spotify link for a profile that does not exist.
 * Distinct from SpotifyNotConnectedException, which means the profile exists but has no link.
 */
final class SpotifyProfileNotFoundException extends SpotifyDomainException
{
    public function __construct(string $profileId)
    {
        parent::__construct(sprintf('No Spotify link found for profile "%s".', $profileId));
    }
}

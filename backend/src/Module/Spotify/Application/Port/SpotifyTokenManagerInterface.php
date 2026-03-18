<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Port;

use App\Module\Spotify\Domain\SpotifyAccountLink;

/**
 * Ensures valid access token for a profile; refreshes and persists when needed.
 */
interface SpotifyTokenManagerInterface
{
    /**
     * Returns the account link with a valid access token (refreshes if expired).
     *
     * @throws \App\Module\Spotify\Domain\Exception\SpotifyNotConnectedException when no link
     * @throws \App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException when refresh fails
     */
    public function getValidLinkForProfile(string $profileId): SpotifyAccountLink;
}

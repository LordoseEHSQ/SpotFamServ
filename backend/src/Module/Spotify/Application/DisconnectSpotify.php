<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Port\SpotifyAccountLinkRepositoryInterface;
use App\Module\Spotify\Domain\Exception\SpotifyProfileNotFoundException;

final readonly class DisconnectSpotify
{
    public function __construct(
        private SpotifyAccountLinkRepositoryInterface $linkRepository,
    ) {
    }

    /**
     * Removes the Spotify account link for a profile, effectively disconnecting Spotify.
     * The user can reconnect via the OAuth flow at any time.
     */
    public function __invoke(string $profileId): void
    {
        $link = $this->linkRepository->findByProfileId($profileId);
        if ($link === null) {
            throw new SpotifyProfileNotFoundException($profileId);
        }
        $this->linkRepository->delete($link);
    }
}

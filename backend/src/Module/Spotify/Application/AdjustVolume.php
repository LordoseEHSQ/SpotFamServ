<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;

final readonly class AdjustVolume
{
    public function __construct(
        private SpotifyTokenManagerInterface $tokenManager,
        private SpotifyApiClientInterface $apiClient,
    ) {
    }

    /**
     * Adjusts playback volume by $delta (e.g. +10 or -10), clamped to [0, 100].
     * Silently does nothing when no active playback state is available.
     */
    public function __invoke(string $profileId, int $delta): void
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        $token = $link->getAccessToken();
        $state = $this->apiClient->getCurrentPlayback($token);
        if ($state === null) {
            return;
        }
        $newVolume = max(0, min(100, $state->volumePercent + $delta));
        $this->apiClient->setVolume($token, $newVolume);
    }
}

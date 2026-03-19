<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;

final readonly class SkipToNext
{
    public function __construct(
        private SpotifyTokenManagerInterface $tokenManager,
        private SpotifyApiClientInterface $apiClient,
    ) {
    }

    public function __invoke(string $profileId, ?string $deviceId = null): void
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        $this->apiClient->nextTrack($link->getAccessToken(), $deviceId);
    }
}

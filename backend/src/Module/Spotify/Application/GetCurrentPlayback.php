<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Dto\SpotifyPlaybackStateDto;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;

final readonly class GetCurrentPlayback
{
    public function __construct(
        private SpotifyTokenManagerInterface $tokenManager,
        private SpotifyApiClientInterface $apiClient,
    ) {
    }

    public function __invoke(string $profileId): ?SpotifyPlaybackStateDto
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        return $this->apiClient->getCurrentPlayback($link->getAccessToken());
    }
}

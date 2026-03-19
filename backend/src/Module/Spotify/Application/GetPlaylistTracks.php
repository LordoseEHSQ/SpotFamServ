<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Dto\SpotifyPlaylistTracksDto;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;

final readonly class GetPlaylistTracks
{
    public function __construct(
        private SpotifyTokenManagerInterface $tokenManager,
        private SpotifyApiClientInterface $apiClient,
    ) {
    }

    public function __invoke(string $profileId, string $playlistId, int $offset = 0, int $limit = 50): SpotifyPlaylistTracksDto
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        return $this->apiClient->getPlaylistTracks($link->getAccessToken(), $playlistId, $offset, $limit);
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Dto\SpotifyPlaylistDto;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;

final readonly class GetUserPlaylists
{
    public function __construct(
        private SpotifyTokenManagerInterface $tokenManager,
        private SpotifyApiClientInterface $apiClient,
    ) {
    }

    /**
     * @return list<SpotifyPlaylistDto>
     */
    public function __invoke(string $profileId, int $offset = 0, int $limit = 50): array
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        return $this->apiClient->getUserPlaylists($link->getAccessToken(), $offset, $limit);
    }
}

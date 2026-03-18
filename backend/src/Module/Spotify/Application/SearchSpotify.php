<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Dto\SpotifySearchResultDto;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;

final readonly class SearchSpotify
{
    public function __construct(
        private SpotifyTokenManagerInterface $tokenManager,
        private SpotifyApiClientInterface $apiClient,
    ) {
    }

    public function __invoke(string $profileId, string $query, string $types = 'playlist,track'): SpotifySearchResultDto
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        return $this->apiClient->search($link->getAccessToken(), $query, $types);
    }
}

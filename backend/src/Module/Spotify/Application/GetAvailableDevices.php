<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Dto\SpotifyDeviceDto;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;

final readonly class GetAvailableDevices
{
    public function __construct(
        private SpotifyTokenManagerInterface $tokenManager,
        private SpotifyApiClientInterface $apiClient,
    ) {
    }

    /**
     * @return list<SpotifyDeviceDto>
     */
    public function __invoke(string $profileId): array
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        return $this->apiClient->getAvailableDevices($link->getAccessToken());
    }
}

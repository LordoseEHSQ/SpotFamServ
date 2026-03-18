<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;
use App\Module\Spotify\Domain\Exception\SpotifyNoDeviceException;

/**
 * Start playback of a playlist URI on a device. Transfers to device first if needed.
 */
final readonly class StartPlayback
{
    public function __construct(
        private SpotifyTokenManagerInterface $tokenManager,
        private SpotifyApiClientInterface $apiClient,
        private FamilyProfileRepositoryInterface $profileRepository,
    ) {
    }

    public function __invoke(string $profileId, string $contextUri, ?string $deviceId = null): void
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        $accessToken = $link->getAccessToken();

        $resolvedDeviceId = $deviceId;
        if ($resolvedDeviceId === null || $resolvedDeviceId === '') {
            $profile = $this->profileRepository->find($profileId);
            $resolvedDeviceId = $profile?->getDefaultSpotifyDeviceId();
            if ($resolvedDeviceId === null || $resolvedDeviceId === '') {
                throw new SpotifyNoDeviceException('No device specified. Set default device or pass device_id.');
            }
        }

        $this->apiClient->transferPlayback($accessToken, $resolvedDeviceId);
        $this->apiClient->startPlayback($accessToken, $contextUri, $resolvedDeviceId);
    }
}

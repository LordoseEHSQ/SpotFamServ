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

    public function __invoke(string $profileId, string $contextUri, ?string $deviceId = null, ?string $deviceName = null): void
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        $accessToken = $link->getAccessToken();

        // Explicit device id (e.g. a reader's mapped room box): play directly. The stored id
        // can go stale when the box reconnects, so re-resolve by name once on failure. We do
        // NOT persist here (StartPlayback is owner-agnostic – the caller owns the mapping).
        if ($deviceId !== null && $deviceId !== '') {
            try {
                $this->play($accessToken, $contextUri, $deviceId);
            } catch (SpotifyNoDeviceException $e) {
                $newDeviceId = $this->reResolveByName($accessToken, $deviceName, $deviceId);
                if ($newDeviceId === null) {
                    throw $e;
                }
                $this->play($accessToken, $contextUri, $newDeviceId);
            }
            return;
        }

        $profile = $this->profileRepository->find($profileId);
        $defaultDeviceId = $profile?->getDefaultSpotifyDeviceId();
        if ($profile === null || $defaultDeviceId === null || $defaultDeviceId === '') {
            throw new SpotifyNoDeviceException('No device specified. Set default device or pass device_id.');
        }

        try {
            $this->play($accessToken, $contextUri, $defaultDeviceId);
        } catch (SpotifyNoDeviceException $e) {
            // The stored default device id can become stale (Spotify hands out a new id when the
            // Wobie Box reconnects). Re-resolve by the stored device name once and retry.
            $newDeviceId = $this->reResolveByName($accessToken, $profile->getDefaultDeviceName(), $defaultDeviceId);
            if ($newDeviceId === null) {
                throw $e;
            }
            $profile->setDefaultDevice($newDeviceId, $profile->getDefaultDeviceName());
            $this->profileRepository->save($profile);
            $this->play($accessToken, $contextUri, $newDeviceId);
        }
    }

    private function play(string $accessToken, string $contextUri, string $deviceId): void
    {
        $this->apiClient->transferPlayback($accessToken, $deviceId);
        $this->apiClient->startPlayback($accessToken, $contextUri, $deviceId);
    }

    /**
     * Finds a currently available device whose name matches the stored default name
     * but has a different (fresh) device id. Returns null if no usable match exists.
     */
    private function reResolveByName(string $accessToken, ?string $name, string $staleDeviceId): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        $target = mb_strtolower(trim($name));
        foreach ($this->apiClient->getAvailableDevices($accessToken) as $device) {
            if ($device->id !== $staleDeviceId && mb_strtolower(trim($device->name)) === $target) {
                return $device->id;
            }
        }
        return null;
    }
}

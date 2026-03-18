<?php

declare(strict_types=1);

namespace App\Module\Device\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Device\Application\Port\DeviceDiscoveryRunRepositoryInterface;
use App\Module\Device\Application\Port\SpotifyDeviceRepositoryInterface;
use App\Module\Device\Domain\DeviceDiscoveryRun;
use App\Module\Device\Domain\SpotifyDevice;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;
use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class RunDeviceDiscovery
{
    public function __construct(
        private SpotifyDeviceRepositoryInterface $deviceRepository,
        private DeviceDiscoveryRunRepositoryInterface $runRepository,
        private ActivityLogRepositoryInterface $activityRepository,
        private SpotifyApiClientInterface $spotifyClient,
        private SpotifyTokenManagerInterface $tokenManager,
        private FamilyProfileRepositoryInterface $profileRepository,
    ) {}

    public function __invoke(?string $profileId = null): DeviceDiscoveryRun
    {
        $scopeProfileUuid = $profileId !== null ? Uuid::fromString($profileId) : null;
        $run = new DeviceDiscoveryRun(
            $scopeProfileUuid !== null ? DeviceDiscoveryRun::SCOPE_PROFILE : DeviceDiscoveryRun::SCOPE_GLOBAL,
            $scopeProfileUuid,
        );
        $this->runRepository->save($run);

        $profilesToScan = [];

        if ($scopeProfileUuid !== null) {
            $profile = $this->profileRepository->find((string) $scopeProfileUuid);
            if ($profile !== null) {
                $profilesToScan[] = $profile;
            }
        } else {
            $profilesToScan = $this->profileRepository->findAll();
        }

        $rawDevicesAll = [];
        $errorMessage  = null;

        foreach ($profilesToScan as $profile) {
            try {
                $token = $this->tokenManager->getValidToken($profile->getId());
                if ($token === null) {
                    continue;
                }
                $devices = $this->spotifyClient->getDevices($token);
                foreach ($devices as $device) {
                    $rawDevicesAll[$device->id] = [
                        'id'        => $device->id,
                        'name'      => $device->name,
                        'type'      => $device->type,
                        'is_active' => $device->isActive,
                    ];
                }
            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        }

        $found     = count($rawDevicesAll);
        $available = 0;
        $newCount  = 0;

        foreach ($rawDevicesAll as $rawDevice) {
            $existing = $this->deviceRepository->findBySpotifyDeviceId($rawDevice['id']);
            if ($existing === null) {
                $existing = new SpotifyDevice($rawDevice['id'], $rawDevice['name'], $rawDevice['type'] ?? null);
                $newCount++;
            }
            $existing->markSeen(true, $run->getId());
            $this->deviceRepository->save($existing);
            $available++;
        }

        $status = match(true) {
            $errorMessage !== null && $found === 0 => DeviceDiscoveryRun::STATUS_FAILED,
            $found === 0                           => DeviceDiscoveryRun::STATUS_NO_DEVICES,
            $errorMessage !== null                 => DeviceDiscoveryRun::STATUS_PARTIAL,
            default                                => DeviceDiscoveryRun::STATUS_SUCCESS,
        };

        $run->finish($status, $found, $available, $newCount, $errorMessage, array_values($rawDevicesAll));
        $this->runRepository->save($run);

        $this->activityRepository->append(new ActivityLog(
            ActivityLog::TYPE_DEVICE_DISCOVERED,
            sprintf('Discovery abgeschlossen: %d Gerät(e) gefunden, %d neu.', $found, $newCount),
            ActivityLog::SEVERITY_INFO,
            $scopeProfileUuid,
            'device_discovery_run',
            (string) $run->getId(),
            ['found' => $found, 'available' => $available, 'new' => $newCount, 'status' => $status],
        ));

        return $run;
    }
}

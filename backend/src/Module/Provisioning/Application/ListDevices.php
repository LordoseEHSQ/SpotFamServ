<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application;

use App\Module\Provisioning\Application\Port\DetectedDeviceRepositoryInterface;
use App\Module\Provisioning\Application\Port\FlashJobRepositoryInterface;
use App\Module\Provisioning\Domain\DetectedDevice;
use App\Module\Provisioning\Domain\FlashJob;

/**
 * Listet alle erkannten Geräte inkl. des neuesten Jobs (GET /api/v1/provisioning/devices).
 *
 * @return list<array{device: DetectedDevice, latestJob: FlashJob|null}>
 */
final readonly class ListDevices
{
    public function __construct(
        private DetectedDeviceRepositoryInterface $devices,
        private FlashJobRepositoryInterface $jobs,
    ) {
    }

    /** @return list<array{device: DetectedDevice, latestJob: FlashJob|null}> */
    public function __invoke(): array
    {
        $devices = $this->devices->findAll();
        $result  = [];

        foreach ($devices as $device) {
            $result[] = [
                'device'    => $device,
                'latestJob' => $this->jobs->findLatestForDevice((string) $device->getId()),
            ];
        }

        return $result;
    }
}

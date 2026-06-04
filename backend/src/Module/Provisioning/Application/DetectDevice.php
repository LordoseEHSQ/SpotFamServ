<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Provisioning\Application\Port\DetectedDeviceRepositoryInterface;
use App\Module\Provisioning\Domain\DetectedDevice;

/**
 * Upsert eines erkannten ESP32-Geräts (POST /api/v1/provisioning/devices/detect).
 * ActivityLog nur beim ersten Erkennen (Neuanlage), nicht bei jedem Poll-Aufruf.
 */
final readonly class DetectDevice
{
    public function __construct(
        private DetectedDeviceRepositoryInterface $devices,
        private ActivityLogRepositoryInterface $activityLog,
    ) {
    }

    public function __invoke(
        string $port,
        string $chip,
        string $chipDescription,
        string $mac,
        string $flashSize,
    ): DetectDeviceResult {
        $mac    = strtolower(trim($mac));
        $port   = trim($port);
        $chip   = trim($chip);
        $flashSize = trim($flashSize);

        $existing = $this->devices->findByMac($mac);

        if ($existing === null) {
            $device = new DetectedDevice($port, $chip, $chipDescription, $mac, $flashSize);
            $this->devices->save($device);

            $this->activityLog->append(new ActivityLog(
                ActivityLog::TYPE_PROVISIONING_DEVICE_DETECTED,
                sprintf('Neues ESP32-Gerät erkannt: MAC %s an Port %s.', $mac, $port),
                ActivityLog::SEVERITY_INFO,
                null,
                'detected_device',
                $device->getId(),
                ['mac' => $mac, 'chip' => $chip, 'port' => $port],
            ));

            return new DetectDeviceResult((string) $device->getId(), $device->getStatus(), true);
        }

        // Gerät bereits bekannt: Eigenschaften aktualisieren, lastSeenAt erneuern
        $existing->updateDetection($port, $chip, $chipDescription, $flashSize);
        $this->devices->save($existing);

        return new DetectDeviceResult((string) $existing->getId(), $existing->getStatus(), false);
    }
}

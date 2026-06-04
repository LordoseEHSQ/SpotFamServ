<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application;

use App\Module\Provisioning\Application\Port\DetectedDeviceRepositoryInterface;
use App\Module\Provisioning\Application\Port\FlashJobRepositoryInterface;
use App\Module\Provisioning\Domain\FlashJob;

/**
 * Liefert den ältesten pending-Job für ein Gerät (GET /api/v1/provisioning/jobs/next).
 * Setzt den Status NICHT auf running – der Agent meldet das separat über UpdateJobStatus.
 * Gibt null zurück, wenn kein offener Job vorhanden ist (→ 204).
 */
final readonly class GetNextJob
{
    public function __construct(
        private DetectedDeviceRepositoryInterface $devices,
        private FlashJobRepositoryInterface $jobs,
    ) {
    }

    public function __invoke(string $deviceId): ?FlashJob
    {
        $device = $this->devices->findById($deviceId);
        if ($device === null) {
            throw ProvisioningException::deviceNotFound($deviceId);
        }

        return $this->jobs->findOldestPendingForDevice($deviceId);
    }
}

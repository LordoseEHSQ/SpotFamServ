<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Provisioning\Application\Port\DetectedDeviceRepositoryInterface;
use App\Module\Provisioning\Application\Port\FlashArtifactRepositoryInterface;
use App\Module\Provisioning\Application\Port\FlashJobRepositoryInterface;
use App\Module\Provisioning\Domain\FlashJob;
use App\Shared\Application\Port\TransactionRunnerInterface;

/**
 * Legt einen neuen Flash-Job an (POST /api/v1/provisioning/jobs).
 * Die 409-Prüfung (aktiver Job vorhanden?) und die Anlage werden atomar im TransactionRunner ausgeführt.
 */
final readonly class CreateJob
{
    public function __construct(
        private DetectedDeviceRepositoryInterface $devices,
        private FlashArtifactRepositoryInterface $artifacts,
        private FlashJobRepositoryInterface $jobs,
        private ActivityLogRepositoryInterface $activityLog,
        private TransactionRunnerInterface $transactionRunner,
    ) {
    }

    public function __invoke(string $deviceId, string $artifactId): FlashJob
    {
        // Existenz-Checks außerhalb der Transaktion (read-only, keine Race-Condition)
        $device = $this->devices->findById($deviceId);
        if ($device === null) {
            throw ProvisioningException::deviceNotFound($deviceId);
        }

        $artifact = $this->artifacts->findById($artifactId);
        if ($artifact === null) {
            throw ProvisioningException::artifactNotFound($artifactId);
        }

        return $this->transactionRunner->run(function () use ($device, $artifact, $deviceId, $artifactId): FlashJob {
            // 409-Prüfung und Anlage atomar
            $activeJob = $this->jobs->findActiveForDevice($deviceId);
            if ($activeJob !== null) {
                throw ProvisioningException::activeJobExists($deviceId);
            }

            $job = new FlashJob($device, $artifact);
            $this->jobs->save($job);

            $this->activityLog->append(new ActivityLog(
                ActivityLog::TYPE_FLASH_JOB_CREATED,
                sprintf('Flash-Job angelegt für Gerät %s mit Artefakt %s.', $deviceId, $artifactId),
                ActivityLog::SEVERITY_INFO,
                null,
                'flash_job',
                $job->getId(),
                [
                    'device_id'   => $deviceId,
                    'artifact_id' => $artifactId,
                    'board'       => $artifact->getBoard(),
                    'version'     => $artifact->getVersion(),
                ],
            ));

            return $job;
        });
    }
}

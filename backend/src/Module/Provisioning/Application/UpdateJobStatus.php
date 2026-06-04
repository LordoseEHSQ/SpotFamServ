<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Provisioning\Application\Port\DetectedDeviceRepositoryInterface;
use App\Module\Provisioning\Application\Port\FlashJobRepositoryInterface;
use App\Module\Provisioning\Domain\FlashJob;

/**
 * Aktualisiert den Status eines FlashJobs (POST /api/v1/provisioning/jobs/{jobId}/status).
 * Bei Abschluss (success|failed) wird device.status auf 'idle' zurückgesetzt.
 */
final readonly class UpdateJobStatus
{
    public function __construct(
        private FlashJobRepositoryInterface $jobs,
        private DetectedDeviceRepositoryInterface $devices,
        private ActivityLogRepositoryInterface $activityLog,
    ) {
    }

    public function __invoke(string $jobId, string $newStatus, ?int $progress, ?string $message): void
    {
        $allowed = [FlashJob::STATUS_RUNNING, FlashJob::STATUS_SUCCESS, FlashJob::STATUS_FAILED];
        if (!in_array($newStatus, $allowed, true)) {
            throw ProvisioningException::invalidRequest(
                sprintf('Ungültiger Status "%s". Erlaubt: %s.', $newStatus, implode(', ', $allowed)),
            );
        }

        $job = $this->jobs->findById($jobId);
        if ($job === null) {
            throw ProvisioningException::jobNotFound($jobId);
        }

        try {
            $job->applyStatusUpdate($newStatus, $progress, $message);
        } catch (\DomainException $e) {
            throw ProvisioningException::invalidStatusTransition($job->getStatus(), $newStatus);
        }

        // Gerät bei Abschluss auf idle setzen
        if ($newStatus === FlashJob::STATUS_SUCCESS || $newStatus === FlashJob::STATUS_FAILED) {
            $device = $job->getDevice();
            $device->markIdle();
            $this->devices->save($device);
        }

        $this->jobs->save($job);

        $logType = match ($newStatus) {
            FlashJob::STATUS_RUNNING => ActivityLog::TYPE_FLASH_JOB_STARTED,
            FlashJob::STATUS_SUCCESS => ActivityLog::TYPE_FLASH_JOB_SUCCEEDED,
            FlashJob::STATUS_FAILED  => ActivityLog::TYPE_FLASH_JOB_FAILED,
        };

        $this->activityLog->append(new ActivityLog(
            $logType,
            sprintf('Flash-Job %s: Status %s.', $jobId, $newStatus),
            $newStatus === FlashJob::STATUS_FAILED ? ActivityLog::SEVERITY_WARNING : ActivityLog::SEVERITY_INFO,
            null,
            'flash_job',
            $jobId,
            array_filter([
                'device_id'   => $job->getDevice()->getId(),
                'artifact_id' => $job->getArtifact()->getId(),
                'progress'    => $progress,
                'message'     => $message,
            ], fn (mixed $v) => $v !== null),
        ));
    }
}

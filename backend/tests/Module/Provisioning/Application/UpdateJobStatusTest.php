<?php

declare(strict_types=1);

namespace App\Tests\Module\Provisioning\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Provisioning\Application\Port\DetectedDeviceRepositoryInterface;
use App\Module\Provisioning\Application\Port\FlashJobRepositoryInterface;
use App\Module\Provisioning\Application\ProvisioningException;
use App\Module\Provisioning\Application\UpdateJobStatus;
use App\Module\Provisioning\Domain\DetectedDevice;
use App\Module\Provisioning\Domain\FlashArtifact;
use App\Module\Provisioning\Domain\FlashJob;
use PHPUnit\Framework\TestCase;

class UpdateJobStatusTest extends TestCase
{
    private FlashJobRepositoryInterface $jobs;
    private DetectedDeviceRepositoryInterface $devices;
    private ActivityLogRepositoryInterface $activityLog;

    protected function setUp(): void
    {
        $this->jobs        = $this->createMock(FlashJobRepositoryInterface::class);
        $this->devices     = $this->createMock(DetectedDeviceRepositoryInterface::class);
        $this->activityLog = $this->createMock(ActivityLogRepositoryInterface::class);
    }

    private function useCase(): UpdateJobStatus
    {
        return new UpdateJobStatus($this->jobs, $this->devices, $this->activityLog);
    }

    private function makeJob(): FlashJob
    {
        $device   = new DetectedDevice('COM3', 'ESP32', 'ESP32-D0WD-V3', 'aa:bb:cc:dd:ee:ff', '4MB');
        $artifact = new FlashArtifact('esp32-wroom-32', 'stable', '1.0.0', 'firmware.bin', str_repeat('a', 64), 'ESP32-D0WD-V3', 1024);
        return new FlashJob($device, $artifact);
    }

    public function test_pending_to_running_transition(): void
    {
        $job = $this->makeJob();
        $this->jobs->method('findById')->willReturn($job);
        $this->jobs->expects($this->once())->method('save');
        $this->activityLog->expects($this->once())->method('append')
            ->with($this->callback(fn (ActivityLog $l) => $l->getActivityType() === ActivityLog::TYPE_FLASH_JOB_STARTED));

        $this->useCase()->__invoke('job-id', FlashJob::STATUS_RUNNING, 10, null);

        $this->assertSame(FlashJob::STATUS_RUNNING, $job->getStatus());
        $this->assertSame(10, $job->getProgress());
    }

    public function test_running_to_success_resets_device_to_idle(): void
    {
        $job = $this->makeJob();
        // Erst auf running setzen
        $job->applyStatusUpdate(FlashJob::STATUS_RUNNING, null, null);

        $this->jobs->method('findById')->willReturn($job);
        $this->devices->expects($this->once())->method('save');
        $this->activityLog->expects($this->once())->method('append')
            ->with($this->callback(fn (ActivityLog $l) => $l->getActivityType() === ActivityLog::TYPE_FLASH_JOB_SUCCEEDED));

        $this->useCase()->__invoke('job-id', FlashJob::STATUS_SUCCESS, 100, null);

        $this->assertSame(FlashJob::STATUS_SUCCESS, $job->getStatus());
        $this->assertSame('idle', $job->getDevice()->getStatus());
    }

    public function test_running_to_failed_logs_warning(): void
    {
        $job = $this->makeJob();
        $job->applyStatusUpdate(FlashJob::STATUS_RUNNING, null, null);

        $this->jobs->method('findById')->willReturn($job);
        $this->activityLog->expects($this->once())->method('append')
            ->with($this->callback(fn (ActivityLog $l) => $l->getActivityType() === ActivityLog::TYPE_FLASH_JOB_FAILED
                && $l->getSeverity() === ActivityLog::SEVERITY_WARNING));

        $this->useCase()->__invoke('job-id', FlashJob::STATUS_FAILED, null, 'Flash timeout');

        $this->assertSame(FlashJob::STATUS_FAILED, $job->getStatus());
        $this->assertSame('Flash timeout', $job->getMessage());
    }

    public function test_throws_404_when_job_not_found(): void
    {
        $this->jobs->method('findById')->willReturn(null);

        try {
            $this->useCase()->__invoke('missing-job', FlashJob::STATUS_RUNNING, null, null);
            $this->fail('ProvisioningException erwartet');
        } catch (ProvisioningException $e) {
            $this->assertSame(404, $e->statusCode);
            $this->assertSame('job_not_found', $e->errorCode);
        }
    }

    public function test_invalid_transition_throws_422(): void
    {
        $job = $this->makeJob();
        // pending→success ist kein erlaubter Übergang
        $this->jobs->method('findById')->willReturn($job);

        try {
            $this->useCase()->__invoke('job-id', FlashJob::STATUS_SUCCESS, null, null);
            $this->fail('ProvisioningException erwartet');
        } catch (ProvisioningException $e) {
            $this->assertSame(422, $e->statusCode);
            $this->assertSame('invalid_status_transition', $e->errorCode);
        }
    }

    public function test_invalid_status_string_throws_400(): void
    {
        $job = $this->makeJob();
        $this->jobs->method('findById')->willReturn($job);

        try {
            $this->useCase()->__invoke('job-id', 'foobar', null, null);
            $this->fail('ProvisioningException erwartet');
        } catch (ProvisioningException $e) {
            $this->assertSame(400, $e->statusCode);
        }
    }
}

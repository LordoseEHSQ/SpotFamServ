<?php

declare(strict_types=1);

namespace App\Tests\Module\Provisioning\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\Provisioning\Application\CreateJob;
use App\Module\Provisioning\Application\Port\DetectedDeviceRepositoryInterface;
use App\Module\Provisioning\Application\Port\FlashArtifactRepositoryInterface;
use App\Module\Provisioning\Application\Port\FlashJobRepositoryInterface;
use App\Module\Provisioning\Application\ProvisioningException;
use App\Module\Provisioning\Domain\DetectedDevice;
use App\Module\Provisioning\Domain\FlashArtifact;
use App\Module\Provisioning\Domain\FlashJob;
use App\Shared\Application\Port\TransactionRunnerInterface;
use PHPUnit\Framework\TestCase;

class CreateJobTest extends TestCase
{
    private DetectedDeviceRepositoryInterface $devices;
    private FlashArtifactRepositoryInterface $artifacts;
    private FlashJobRepositoryInterface $jobs;
    private ActivityLogRepositoryInterface $activityLog;
    private TransactionRunnerInterface $transactionRunner;

    protected function setUp(): void
    {
        $this->devices           = $this->createMock(DetectedDeviceRepositoryInterface::class);
        $this->artifacts         = $this->createMock(FlashArtifactRepositoryInterface::class);
        $this->jobs              = $this->createMock(FlashJobRepositoryInterface::class);
        $this->activityLog       = $this->createMock(ActivityLogRepositoryInterface::class);
        // Synchroner TransactionRunner für Unit-Tests
        $this->transactionRunner = new class implements TransactionRunnerInterface {
            public function run(callable $callback): mixed
            {
                return $callback();
            }
        };
    }

    private function useCase(): CreateJob
    {
        return new CreateJob(
            $this->devices,
            $this->artifacts,
            $this->jobs,
            $this->activityLog,
            $this->transactionRunner,
        );
    }

    private function makeDevice(): DetectedDevice
    {
        return new DetectedDevice('COM3', 'ESP32', 'ESP32-D0WD-V3', 'aa:bb:cc:dd:ee:ff', '4MB');
    }

    private function makeArtifact(): FlashArtifact
    {
        return new FlashArtifact('esp32-wroom-32', 'stable', '1.0.0', 'firmware.bin', str_repeat('a', 64), 'ESP32-D0WD-V3', 1024);
    }

    public function test_creates_pending_job_successfully(): void
    {
        $device   = $this->makeDevice();
        $artifact = $this->makeArtifact();

        $this->devices->method('findById')->willReturn($device);
        $this->artifacts->method('findById')->willReturn($artifact);
        $this->jobs->method('findActiveForDevice')->willReturn(null);
        $this->jobs->expects($this->once())->method('save');
        $this->activityLog->expects($this->once())->method('append');

        $job = $this->useCase()->__invoke('device-uuid', 'artifact-uuid');

        $this->assertSame(FlashJob::STATUS_PENDING, $job->getStatus());
        $this->assertSame(0, $job->getProgress());
    }

    public function test_throws_404_when_device_not_found(): void
    {
        $this->devices->method('findById')->willReturn(null);

        $this->expectException(ProvisioningException::class);
        $this->expectExceptionCode(0);

        $exception = null;
        try {
            $this->useCase()->__invoke('missing-device', 'artifact-uuid');
        } catch (ProvisioningException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertSame(404, $exception->statusCode);
        $this->assertSame('device_not_found', $exception->errorCode);

        throw $exception;
    }

    public function test_throws_404_when_artifact_not_found(): void
    {
        $this->devices->method('findById')->willReturn($this->makeDevice());
        $this->artifacts->method('findById')->willReturn(null);

        try {
            $this->useCase()->__invoke('device-uuid', 'missing-artifact');
            $this->fail('ProvisioningException erwartet');
        } catch (ProvisioningException $e) {
            $this->assertSame(404, $e->statusCode);
            $this->assertSame('artifact_not_found', $e->errorCode);
        }
    }

    public function test_throws_409_when_active_job_exists(): void
    {
        $device   = $this->makeDevice();
        $artifact = $this->makeArtifact();
        $activeJob = new FlashJob($device, $artifact);

        $this->devices->method('findById')->willReturn($device);
        $this->artifacts->method('findById')->willReturn($artifact);
        $this->jobs->method('findActiveForDevice')->willReturn($activeJob);

        try {
            $this->useCase()->__invoke('device-uuid', 'artifact-uuid');
            $this->fail('ProvisioningException erwartet');
        } catch (ProvisioningException $e) {
            $this->assertSame(409, $e->statusCode);
            $this->assertSame('active_job_exists', $e->errorCode);
        }
    }
}

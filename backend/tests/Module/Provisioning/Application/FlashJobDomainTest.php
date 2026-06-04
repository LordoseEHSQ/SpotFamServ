<?php

declare(strict_types=1);

namespace App\Tests\Module\Provisioning\Application;

use App\Module\Provisioning\Domain\DetectedDevice;
use App\Module\Provisioning\Domain\FlashArtifact;
use App\Module\Provisioning\Domain\FlashJob;
use PHPUnit\Framework\TestCase;

/**
 * Reine Domain-Tests für FlashJob-Statusübergänge.
 */
class FlashJobDomainTest extends TestCase
{
    private function makeJob(): FlashJob
    {
        $device   = new DetectedDevice('COM3', 'ESP32', 'ESP32-D0WD-V3', 'aa:bb:cc:dd:ee:ff', '4MB');
        $artifact = new FlashArtifact('esp32-wroom-32', 'stable', '1.0.0', 'firmware.bin', str_repeat('a', 64), 'ESP32-D0WD-V3', 1024);
        return new FlashJob($device, $artifact);
    }

    public function test_new_job_is_pending(): void
    {
        $job = $this->makeJob();
        $this->assertSame(FlashJob::STATUS_PENDING, $job->getStatus());
        $this->assertTrue($job->isActive());
    }

    public function test_pending_to_running(): void
    {
        $job = $this->makeJob();
        $job->applyStatusUpdate(FlashJob::STATUS_RUNNING, 5, null);
        $this->assertSame(FlashJob::STATUS_RUNNING, $job->getStatus());
        $this->assertSame(5, $job->getProgress());
        $this->assertTrue($job->isActive());
    }

    public function test_running_to_success(): void
    {
        $job = $this->makeJob();
        $job->applyStatusUpdate(FlashJob::STATUS_RUNNING, null, null);
        $job->applyStatusUpdate(FlashJob::STATUS_SUCCESS, 100, 'OK');
        $this->assertSame(FlashJob::STATUS_SUCCESS, $job->getStatus());
        $this->assertFalse($job->isActive());
    }

    public function test_running_to_failed(): void
    {
        $job = $this->makeJob();
        $job->applyStatusUpdate(FlashJob::STATUS_RUNNING, null, null);
        $job->applyStatusUpdate(FlashJob::STATUS_FAILED, null, 'Timeout');
        $this->assertSame(FlashJob::STATUS_FAILED, $job->getStatus());
        $this->assertFalse($job->isActive());
    }

    public function test_pending_to_success_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $job = $this->makeJob();
        $job->applyStatusUpdate(FlashJob::STATUS_SUCCESS, null, null);
    }

    public function test_pending_to_failed_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $job = $this->makeJob();
        $job->applyStatusUpdate(FlashJob::STATUS_FAILED, null, null);
    }

    public function test_success_to_any_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $job = $this->makeJob();
        $job->applyStatusUpdate(FlashJob::STATUS_RUNNING, null, null);
        $job->applyStatusUpdate(FlashJob::STATUS_SUCCESS, null, null);
        $job->applyStatusUpdate(FlashJob::STATUS_RUNNING, null, null);
    }
}

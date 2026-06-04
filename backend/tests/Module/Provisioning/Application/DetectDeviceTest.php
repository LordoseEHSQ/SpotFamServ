<?php

declare(strict_types=1);

namespace App\Tests\Module\Provisioning\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Provisioning\Application\DetectDevice;
use App\Module\Provisioning\Application\Port\DetectedDeviceRepositoryInterface;
use App\Module\Provisioning\Domain\DetectedDevice;
use PHPUnit\Framework\TestCase;

class DetectDeviceTest extends TestCase
{
    private DetectedDeviceRepositoryInterface $devices;
    private ActivityLogRepositoryInterface $activityLog;

    protected function setUp(): void
    {
        $this->devices     = $this->createMock(DetectedDeviceRepositoryInterface::class);
        $this->activityLog = $this->createMock(ActivityLogRepositoryInterface::class);
    }

    private function useCase(): DetectDevice
    {
        return new DetectDevice($this->devices, $this->activityLog);
    }

    public function test_new_device_is_persisted_and_activity_logged(): void
    {
        $this->devices->method('findByMac')->willReturn(null);
        $this->devices->expects($this->once())->method('save');
        $this->activityLog->expects($this->once())->method('append')
            ->with($this->callback(fn (ActivityLog $l) => $l->getActivityType() === ActivityLog::TYPE_PROVISIONING_DEVICE_DETECTED));

        $result = $this->useCase()->__invoke('COM3', 'ESP32-D0WD-V3', 'ESP32-D0WD-V3 (revision v3.0)', 'aa:bb:cc:dd:ee:ff', '4MB');

        $this->assertTrue($result->isNew);
        $this->assertSame(DetectedDevice::STATUS_IDLE, $result->status);
    }

    public function test_existing_device_is_updated_without_activity_log(): void
    {
        $existing = new DetectedDevice('COM3', 'ESP32', 'ESP32', 'aa:bb:cc:dd:ee:ff', '4MB');
        $this->devices->method('findByMac')->willReturn($existing);
        $this->devices->expects($this->once())->method('save');
        $this->activityLog->expects($this->never())->method('append');

        $result = $this->useCase()->__invoke('COM4', 'ESP32-D0WD-V3', 'ESP32-D0WD-V3 updated', 'aa:bb:cc:dd:ee:ff', '8MB');

        $this->assertFalse($result->isNew);
        // Port und FlashSize müssen aktualisiert worden sein
        $this->assertSame('COM4', $existing->getPort());
        $this->assertSame('8MB', $existing->getFlashSize());
    }

    public function test_mac_is_normalized_to_lowercase(): void
    {
        $capturedMac = null;
        $this->devices->method('findByMac')->willReturnCallback(function (string $mac) use (&$capturedMac): ?DetectedDevice {
            $capturedMac = $mac;
            return null;
        });

        $this->useCase()->__invoke('COM3', 'ESP32', 'desc', 'AA:BB:CC:DD:EE:FF', '4MB');

        $this->assertSame('aa:bb:cc:dd:ee:ff', $capturedMac);
    }
}

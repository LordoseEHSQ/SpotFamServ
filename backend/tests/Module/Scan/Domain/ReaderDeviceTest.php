<?php

declare(strict_types=1);

namespace App\Tests\Module\Scan\Domain;

use App\Module\Scan\Domain\ReaderDevice;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class ReaderDeviceTest extends TestCase
{
    public function test_minutes_since_last_seen_returns_null_when_never_seen(): void
    {
        $reader = new ReaderDevice('r-1');
        $clock = new MockClock(new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC')));

        $this->assertNull($reader->minutesSinceLastSeen($clock));
    }

    public function test_minutes_since_last_seen_returns_zero_when_just_seen(): void
    {
        $now = new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC'));
        $clock = new MockClock($now);

        $reader = new ReaderDevice('r-1');
        $reader->touchSeen('1.2.3.4');

        // touchSeen uses real time; override via reflection to control lastSeenAt
        $this->setLastSeenAt($reader, $now);

        $this->assertSame(0, $reader->minutesSinceLastSeen($clock));
    }

    public function test_minutes_since_last_seen_returns_4_for_4_minutes_59_seconds(): void
    {
        $lastSeen = new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC'));
        $now = new \DateTimeImmutable('2024-01-01 12:04:59', new \DateTimeZone('UTC'));
        $clock = new MockClock($now);

        $reader = new ReaderDevice('r-1');
        $this->setLastSeenAt($reader, $lastSeen);

        $this->assertSame(4, $reader->minutesSinceLastSeen($clock));
    }

    public function test_minutes_since_last_seen_returns_5_for_exactly_5_minutes(): void
    {
        $lastSeen = new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC'));
        $now = new \DateTimeImmutable('2024-01-01 12:05:00', new \DateTimeZone('UTC'));
        $clock = new MockClock($now);

        $reader = new ReaderDevice('r-1');
        $this->setLastSeenAt($reader, $lastSeen);

        $this->assertSame(5, $reader->minutesSinceLastSeen($clock));
    }

    public function test_minutes_since_last_seen_returns_59_for_59_minutes(): void
    {
        $lastSeen = new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC'));
        $now = new \DateTimeImmutable('2024-01-01 12:59:30', new \DateTimeZone('UTC'));
        $clock = new MockClock($now);

        $reader = new ReaderDevice('r-1');
        $this->setLastSeenAt($reader, $lastSeen);

        $this->assertSame(59, $reader->minutesSinceLastSeen($clock));
    }

    public function test_minutes_since_last_seen_returns_60_for_exactly_one_hour(): void
    {
        $lastSeen = new \DateTimeImmutable('2024-01-01 12:00:00', new \DateTimeZone('UTC'));
        $now = new \DateTimeImmutable('2024-01-01 13:00:00', new \DateTimeZone('UTC'));
        $clock = new MockClock($now);

        $reader = new ReaderDevice('r-1');
        $this->setLastSeenAt($reader, $lastSeen);

        $this->assertSame(60, $reader->minutesSinceLastSeen($clock));
    }

    private function setLastSeenAt(ReaderDevice $reader, \DateTimeImmutable $dt): void
    {
        $ref = new \ReflectionProperty(ReaderDevice::class, 'lastSeenAt');
        $ref->setValue($reader, $dt);
    }
}

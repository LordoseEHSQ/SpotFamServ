<?php

declare(strict_types=1);

namespace App\Tests\Module\Scan\Application;

use App\Module\Scan\Application\DeleteReader;
use App\Module\Scan\Application\Port\ReaderClaimRepositoryInterface;
use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Application\Port\ScanEventRepositoryInterface;
use App\Module\Scan\Domain\ReaderDevice;
use App\Shared\Application\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class DeleteReaderTest extends TestCase
{
    public function test_happy_path_deletes_events_claims_and_device(): void
    {
        $device = new class('reader-01') extends ReaderDevice {
            public function getId(): ?string { return 'aaaaaaaa-0000-0000-0000-000000000001'; }
        };

        $readers = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $readers->method('findByReaderId')->with('reader-01')->willReturn($device);
        $readers->expects($this->once())->method('delete')->with($device);

        $scanEvents = $this->createMock(ScanEventRepositoryInterface::class);
        $scanEvents->expects($this->once())
            ->method('deleteByReaderDeviceId')
            ->with('aaaaaaaa-0000-0000-0000-000000000001');

        $claims = $this->createMock(ReaderClaimRepositoryInterface::class);
        $claims->expects($this->once())->method('deleteByReaderId')->with('reader-01');

        (new DeleteReader($readers, $scanEvents, $claims))('reader-01');
    }

    public function test_not_found_throws_exception(): void
    {
        $readers = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $readers->method('findByReaderId')->willReturn(null);
        $readers->expects($this->never())->method('delete');

        $scanEvents = $this->createMock(ScanEventRepositoryInterface::class);
        $scanEvents->expects($this->never())->method('deleteByReaderDeviceId');

        $claims = $this->createMock(ReaderClaimRepositoryInterface::class);
        $claims->expects($this->never())->method('deleteByReaderId');

        $this->expectException(NotFoundException::class);
        (new DeleteReader($readers, $scanEvents, $claims))('ghost-reader');
    }

    public function test_device_without_id_skips_event_deletion(): void
    {
        $device = new ReaderDevice('reader-02');

        $readers = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $readers->method('findByReaderId')->with('reader-02')->willReturn($device);
        $readers->expects($this->once())->method('delete')->with($device);

        $scanEvents = $this->createMock(ScanEventRepositoryInterface::class);
        $scanEvents->expects($this->never())->method('deleteByReaderDeviceId');

        $claims = $this->createMock(ReaderClaimRepositoryInterface::class);
        $claims->expects($this->once())->method('deleteByReaderId')->with('reader-02');

        (new DeleteReader($readers, $scanEvents, $claims))('reader-02');
    }

    public function test_claims_are_always_deleted_even_without_scan_events(): void
    {
        $device = new class('reader-03') extends ReaderDevice {
            public function getId(): ?string { return 'bbbbbbbb-0000-0000-0000-000000000002'; }
        };

        $readers = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $readers->method('findByReaderId')->willReturn($device);
        $readers->expects($this->once())->method('delete');

        $scanEvents = $this->createMock(ScanEventRepositoryInterface::class);
        $scanEvents->expects($this->once())->method('deleteByReaderDeviceId')
            ->with('bbbbbbbb-0000-0000-0000-000000000002');

        $claims = $this->createMock(ReaderClaimRepositoryInterface::class);
        $claims->expects($this->once())->method('deleteByReaderId')->with('reader-03');

        (new DeleteReader($readers, $scanEvents, $claims))('reader-03');
    }
}

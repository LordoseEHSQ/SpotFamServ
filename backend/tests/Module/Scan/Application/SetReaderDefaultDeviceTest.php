<?php

declare(strict_types=1);

namespace App\Tests\Module\Scan\Application;

use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Application\SetReaderDefaultDevice;
use App\Module\Scan\Domain\ReaderDevice;
use App\Shared\Application\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class SetReaderDefaultDeviceTest extends TestCase
{
    public function test_sets_default_device_and_saves(): void
    {
        $reader = new ReaderDevice('wohnzimmer-1');
        $repo = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $repo->method('findByReaderId')->with('wohnzimmer-1')->willReturn($reader);
        $repo->expects($this->once())->method('save')->with($reader);

        $result = (new SetReaderDefaultDevice($repo))('wohnzimmer-1', ' box-7 ', ' Wohnzimmer ');

        $this->assertSame('box-7', $result->getDefaultSpotifyDeviceId());
        $this->assertSame('Wohnzimmer', $result->getDefaultDeviceName());
    }

    public function test_clears_default_device(): void
    {
        $reader = new ReaderDevice('wohnzimmer-1');
        $reader->setDefaultDevice('box-7', 'Wohnzimmer');
        $repo = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $repo->method('findByReaderId')->willReturn($reader);
        $repo->expects($this->once())->method('save')->with($reader);

        $result = (new SetReaderDefaultDevice($repo))('wohnzimmer-1', null, null);

        $this->assertNull($result->getDefaultSpotifyDeviceId());
        $this->assertNull($result->getDefaultDeviceName());
    }

    public function test_unknown_reader_throws_not_found(): void
    {
        $repo = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $repo->method('findByReaderId')->willReturn(null);
        $repo->expects($this->never())->method('save');

        $this->expectException(NotFoundException::class);
        (new SetReaderDefaultDevice($repo))('ghost-reader', 'box-7', 'X');
    }
}

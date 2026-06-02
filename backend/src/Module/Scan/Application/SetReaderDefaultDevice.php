<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Domain\ReaderDevice;
use App\Shared\Application\Exception\NotFoundException;

/**
 * Sets (or clears) the default Spotify playback device (room box) for a reader.
 * Drives Reader→Box multi-room (D-015): a scan on this reader then plays on the
 * mapped box instead of the card profile's default device.
 */
final readonly class SetReaderDefaultDevice
{
    public function __construct(
        private ReaderDeviceRepositoryInterface $repository,
    ) {
    }

    public function __invoke(string $readerId, ?string $deviceId, ?string $deviceName = null): ReaderDevice
    {
        $reader = $this->repository->findByReaderId($readerId);
        if ($reader === null) {
            throw new NotFoundException('Reader not found. It registers automatically on first scan.');
        }

        $reader->setDefaultDevice(
            $deviceId !== null ? trim($deviceId) : null,
            $deviceName !== null ? trim($deviceName) : null,
        );
        $this->repository->save($reader);

        return $reader;
    }
}

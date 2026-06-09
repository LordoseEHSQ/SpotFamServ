<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use App\Module\Scan\Application\Port\ReaderClaimRepositoryInterface;
use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Application\Port\ScanEventRepositoryInterface;
use App\Shared\Application\Exception\NotFoundException;

/**
 * Hard-deletes a reader device along with its scan events and claim records.
 * ScanEvents with reader_device_id = NULL (orphan events from pre-registration scans)
 * are NOT deleted by this operation – this is a known and accepted limitation.
 */
final readonly class DeleteReader
{
    public function __construct(
        private ReaderDeviceRepositoryInterface $readers,
        private ScanEventRepositoryInterface $scanEvents,
        private ReaderClaimRepositoryInterface $claims,
    ) {
    }

    public function __invoke(string $readerId): void
    {
        $device = $this->readers->findByReaderId($readerId);
        if ($device === null) {
            throw new NotFoundException(sprintf('Reader "%s" not found.', $readerId));
        }

        $uuid = $device->getId();
        if ($uuid !== null) {
            $this->scanEvents->deleteByReaderDeviceId($uuid);
        }

        $this->claims->deleteByReaderId($readerId);
        $this->readers->delete($device);
    }
}

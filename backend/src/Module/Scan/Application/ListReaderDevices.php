<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Domain\ReaderDevice;

/**
 * Lists all known readers (auto-registered on first scan), for the admin UI where
 * each reader can be mapped to a room box (D-015).
 */
final readonly class ListReaderDevices
{
    public function __construct(
        private ReaderDeviceRepositoryInterface $repository,
    ) {
    }

    /** @return list<ReaderDevice> */
    public function __invoke(): array
    {
        return $this->repository->findAll();
    }
}

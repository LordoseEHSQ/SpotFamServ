<?php

declare(strict_types=1);

namespace App\Module\Scan\Application\Port;

use App\Module\Scan\Domain\ReaderDevice;

interface ReaderDeviceRepositoryInterface
{
    public function findByReaderId(string $readerId): ?ReaderDevice;

    /** @return list<ReaderDevice> */
    public function findAll(): array;

    public function save(ReaderDevice $device): void;

    public function delete(ReaderDevice $device): void;
}

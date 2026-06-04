<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application\Port;

use App\Module\Provisioning\Domain\DetectedDevice;

interface DetectedDeviceRepositoryInterface
{
    public function findByMac(string $mac): ?DetectedDevice;

    public function findById(string $id): ?DetectedDevice;

    /** @return list<DetectedDevice> */
    public function findAll(): array;

    public function save(DetectedDevice $device): void;
}

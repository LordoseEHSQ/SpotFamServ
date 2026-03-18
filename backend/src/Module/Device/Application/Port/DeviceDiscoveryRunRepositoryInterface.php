<?php

declare(strict_types=1);

namespace App\Module\Device\Application\Port;

use App\Module\Device\Domain\DeviceDiscoveryRun;
use Symfony\Component\Uid\Uuid;

interface DeviceDiscoveryRunRepositoryInterface
{
    public function findById(Uuid $id): ?DeviceDiscoveryRun;
    public function findLatest(): ?DeviceDiscoveryRun;

    /** @return DeviceDiscoveryRun[] */
    public function findRecent(int $limit = 10): array;

    public function save(DeviceDiscoveryRun $run): void;
}

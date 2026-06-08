<?php

declare(strict_types=1);

namespace App\Module\Scan\Application\Port;

use App\Module\Scan\Domain\ScanEvent;

interface ScanEventRepositoryInterface
{
    public function append(
        string $cardUidRaw,
        string $outcome,
        ?string $readerId = null,
        ?string $profileId = null,
        ?array $details = null,
        ?string $readerDeviceId = null,
        ?string $rfidCardId = null,
        ?string $familyProfileId = null,
    ): void;

    public function findRecentScan(string $cardUidRaw, int $withinSeconds, ?string $readerId = null): ?ScanEvent;

    /** @return list<ScanEvent> */
    public function findRecent(int $limit = 50, int $offset = 0, ?string $profileId = null, ?string $readerDeviceId = null): array;
}

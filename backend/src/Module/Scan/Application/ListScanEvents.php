<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use App\Module\Scan\Application\Port\ScanEventRepositoryInterface;
use App\Module\Scan\Domain\ScanEvent;

final readonly class ListScanEvents
{
    public function __construct(
        private ScanEventRepositoryInterface $scanEventRepository,
    ) {
    }

    /**
     * @return list<ScanEvent>
     */
    public function __invoke(int $limit = 50, int $offset = 0, ?string $profileId = null): array
    {
        return $this->scanEventRepository->findRecent($limit, $offset, $profileId);
    }
}

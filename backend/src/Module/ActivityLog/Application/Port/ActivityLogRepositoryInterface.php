<?php

declare(strict_types=1);

namespace App\Module\ActivityLog\Application\Port;

use App\Module\ActivityLog\Domain\ActivityLog;
use Symfony\Component\Uid\Uuid;

interface ActivityLogRepositoryInterface
{
    /** @return ActivityLog[] */
    public function findRecent(
        ?Uuid $profileId = null,
        ?string $severity = null,
        int $limit = 50,
        int $offset = 0,
    ): array;

    public function countRecent(
        ?Uuid $profileId = null,
        ?string $severity = null,
    ): int;

    public function append(ActivityLog $entry): void;
}

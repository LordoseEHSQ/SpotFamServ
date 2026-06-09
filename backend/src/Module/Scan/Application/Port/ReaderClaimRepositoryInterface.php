<?php

declare(strict_types=1);

namespace App\Module\Scan\Application\Port;

use App\Module\Scan\Domain\ReaderClaim;

interface ReaderClaimRepositoryInterface
{
    public function findByCodeHash(string $claimCodeHash): ?ReaderClaim;

    public function save(ReaderClaim $claim): void;

    public function deleteByReaderId(string $readerId): void;
}

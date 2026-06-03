<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

final readonly class CreateReaderClaimResult
{
    public function __construct(
        public string $claimCode,
        public \DateTimeImmutable $expiresAt,
        public string $firmwareChannel,
    ) {
    }
}

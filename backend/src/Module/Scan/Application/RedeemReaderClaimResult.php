<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

final readonly class RedeemReaderClaimResult
{
    public function __construct(
        public string $readerId,
        public string $apiKey,
        public string $firmwareChannel,
    ) {
    }
}

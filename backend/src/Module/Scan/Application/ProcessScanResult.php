<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

final readonly class ProcessScanResult
{
    public function __construct(
        public string $outcome,
        public string $message,
    ) {
    }
}

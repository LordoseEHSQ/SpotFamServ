<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application;

final readonly class GetCompletenessResult
{
    /** @param array<int, array{step_key: string, status: string, payload: array|null}> $steps */
    public function __construct(
        public int $percent,
        public array $steps,
        public string $sessionStatus,
    ) {
    }
}

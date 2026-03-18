<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application;

final readonly class SubmitStepRequest
{
    public function __construct(
        public string $stepKey,
        public string $status,
        public ?array $payload = null,
    ) {
    }
}

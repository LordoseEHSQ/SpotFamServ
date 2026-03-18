<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application;

final readonly class SubmitStepResult
{
    /** @param array<int, array{step_key: string, status: string, payload: array|null}> $steps */
    public function __construct(
        public string $currentStep,
        public string $sessionStatus,
        public array $steps,
    ) {
    }
}

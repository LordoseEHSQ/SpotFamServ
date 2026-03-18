<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application\StepHandler;

use App\Module\SetupWizard\Domain\WizardSteps;

/**
 * Passthrough handler for steps that require no server action.
 * Frontend marks them completed; we just accept the payload.
 */
final class PassthroughStepHandler implements StepHandlerInterface
{
    private const PASSTHROUGH_STEPS = [
        WizardSteps::STEP_SPOTIFY_CONNECT,
        WizardSteps::STEP_DEVICES,
        WizardSteps::STEP_PLAYLIST,
        WizardSteps::STEP_RFID_BIND,
        WizardSteps::STEP_SUMMARY,
    ];

    public function supports(string $stepKey): bool
    {
        return \in_array($stepKey, self::PASSTHROUGH_STEPS, true);
    }

    public function handle(string $profileId, string $stepKey, array $payload): void
    {
        // No server action required for this step
    }
}

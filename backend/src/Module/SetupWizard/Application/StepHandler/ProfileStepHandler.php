<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application\StepHandler;

use App\Module\FamilyProfile\Application\UpdateFamilyProfile;
use App\Module\SetupWizard\Domain\WizardSteps;

/**
 * Handles the profile step: updates name and description.
 */
final readonly class ProfileStepHandler implements StepHandlerInterface
{
    public function __construct(
        private UpdateFamilyProfile $updateFamilyProfile,
    ) {
    }

    public function supports(string $stepKey): bool
    {
        return $stepKey === WizardSteps::STEP_PROFILE;
    }

    public function handle(string $profileId, string $stepKey, array $payload): void
    {
        $name = (string) ($payload['name'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('Profile name is required.');
        }
        ($this->updateFamilyProfile)(
            $profileId,
            $name,
            isset($payload['description']) ? (string) $payload['description'] : null,
        );
    }
}

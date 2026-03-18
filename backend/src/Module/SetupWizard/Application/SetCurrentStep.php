<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application;

use App\Module\SetupWizard\Application\Port\ProfileSetupSessionRepositoryInterface;
use App\Module\SetupWizard\Domain\WizardSteps;

/**
 * Set current step (e.g. when user navigates back). Only allows steps in WizardSteps::ALL.
 */
final readonly class SetCurrentStep
{
    public function __construct(
        private ProfileSetupSessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(string $profileId, string $stepKey): GetWizardStateResult
    {
        if (!\in_array($stepKey, WizardSteps::ALL, true)) {
            throw new \InvalidArgumentException('Unknown step: ' . $stepKey);
        }
        $session = $this->sessionRepository->findOrCreateSession($profileId);
        $session->setCurrentStep($stepKey);
        $this->sessionRepository->save($session);
        $steps = $this->sessionRepository->getStepStatuses($session->getId());
        return new GetWizardStateResult(
            $session->getCurrentStep(),
            $session->getStatus(),
            $steps,
            $session->getId(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application;

use App\Module\SetupWizard\Application\Port\ProfileSetupSessionRepositoryInterface;

/**
 * Returns current wizard state; creates session and step rows if missing (resumable).
 */
final readonly class GetWizardState
{
    public function __construct(
        private ProfileSetupSessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(string $profileId): GetWizardStateResult
    {
        $session = $this->sessionRepository->findOrCreateSession($profileId);
        $steps = $this->sessionRepository->getStepStatuses($session->getId());
        return new GetWizardStateResult(
            $session->getCurrentStep(),
            $session->getStatus(),
            $steps,
            $session->getId(),
        );
    }
}

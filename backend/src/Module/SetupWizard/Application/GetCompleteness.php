<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application;

use App\Module\SetupWizard\Application\Port\ProfileSetupSessionRepositoryInterface;
use App\Module\SetupWizard\Domain\WizardSteps;

/**
 * Returns setup completeness: percent and per-step status for display.
 */
final readonly class GetCompleteness
{
    public function __construct(
        private ProfileSetupSessionRepositoryInterface $sessionRepository,
    ) {
    }

    public function __invoke(string $profileId): GetCompletenessResult
    {
        $session = $this->sessionRepository->findByProfileId($profileId);
        if ($session === null) {
            $steps = array_map(fn (string $key) => [
                'step_key' => $key,
                'status' => 'pending',
                'payload' => null,
            ], WizardSteps::ALL);
            return new GetCompletenessResult(0, $steps, 'in_progress');
        }

        $stepRows = $this->sessionRepository->getStepStatuses($session->getId());
        $byKey = [];
        foreach ($stepRows as $row) {
            $byKey[$row['step_key']] = $row;
        }
        $steps = [];
        $completed = 0;
        foreach (WizardSteps::ALL as $key) {
            $row = $byKey[$key] ?? ['step_key' => $key, 'status' => 'pending', 'payload' => null];
            $steps[] = $row;
            if (($row['status'] ?? '') === 'completed') {
                $completed++;
            }
        }
        $total = \count(WizardSteps::ALL);
        $percent = $total > 0 ? (int) round($completed / $total * 100) : 0;

        return new GetCompletenessResult($percent, $steps, $session->getStatus());
    }
}

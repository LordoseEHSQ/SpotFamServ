<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application;

use App\Module\SetupWizard\Application\Port\ProfileSetupSessionRepositoryInterface;
use App\Module\SetupWizard\Application\StepHandler\StepHandlerInterface;
use App\Module\SetupWizard\Domain\Exception\StepValidationException;
use App\Module\SetupWizard\Domain\ProfileSetupSession;
use App\Module\SetupWizard\Domain\ProfileSetupStepStatus;
use App\Module\SetupWizard\Domain\WizardSteps;

/**
 * Submit a wizard step: validate, run step logic via strategy handler, persist status, advance.
 */
final class SubmitStep
{
    /** @param iterable<StepHandlerInterface> $handlers */
    public function __construct(
        private readonly ProfileSetupSessionRepositoryInterface $sessionRepository,
        private readonly iterable $handlers,
    ) {
    }

    public function __invoke(string $profileId, SubmitStepRequest $request): SubmitStepResult
    {
        if (!\in_array($request->stepKey, WizardSteps::ALL, true)) {
            throw new \InvalidArgumentException('Unknown step: ' . $request->stepKey);
        }

        $session = $this->sessionRepository->findOrCreateSession($profileId);
        $sessionId = $session->getId();
        if ($sessionId === null) {
            throw new \RuntimeException('Session has no id.');
        }

        $status = $request->status;
        $payload = $request->payload ?? [];

        if ($status === ProfileSetupStepStatus::STATUS_COMPLETED) {
            try {
                $this->dispatchToHandler($profileId, $request->stepKey, $payload);
            } catch (\Throwable $e) {
                $this->sessionRepository->upsertStepStatus(
                    $sessionId,
                    $request->stepKey,
                    ProfileSetupStepStatus::STATUS_FAILED,
                    ['error' => $e->getMessage()],
                );
                $steps = $this->sessionRepository->getStepStatuses($sessionId);
                throw new StepValidationException($e->getMessage(), $e, $request->stepKey, $steps);
            }
        }

        $this->sessionRepository->upsertStepStatus($sessionId, $request->stepKey, $status, $request->payload);

        if ($status === ProfileSetupStepStatus::STATUS_COMPLETED) {
            $next = WizardSteps::nextStep($request->stepKey);
            if ($next !== null) {
                $session->setCurrentStep($next);
            }
            if ($request->stepKey === WizardSteps::STEP_SUMMARY) {
                $session->setStatus(ProfileSetupSession::STATUS_COMPLETED);
            }
            $this->sessionRepository->save($session);
        }

        $session = $this->sessionRepository->findByProfileId($profileId);
        $steps = $session ? $this->sessionRepository->getStepStatuses($session->getId()) : [];

        return new SubmitStepResult(
            $session?->getCurrentStep() ?? $request->stepKey,
            $session?->getStatus() ?? ProfileSetupSession::STATUS_IN_PROGRESS,
            $steps,
        );
    }

    /** @param array<string, mixed> $payload */
    private function dispatchToHandler(string $profileId, string $stepKey, array $payload): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($stepKey)) {
                $handler->handle($profileId, $stepKey, $payload);
                return;
            }
        }
    }
}

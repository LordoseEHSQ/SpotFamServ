<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Infrastructure\Http;

use App\Module\SetupWizard\Application\GetCompleteness;
use App\Module\SetupWizard\Application\GetWizardState;
use App\Module\SetupWizard\Application\SetCurrentStep;
use App\Module\SetupWizard\Application\SubmitStep;
use App\Module\SetupWizard\Application\SubmitStepRequest;
use App\Module\SetupWizard\Domain\Exception\StepValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/profiles/{profileId}/setup', name: 'api_setup_', format: 'json', requirements: ['profileId' => '%uuid_regex%'])]
final class SetupWizardController
{
    public function __construct(
        private readonly GetWizardState $getWizardState,
        private readonly SubmitStep $submitStep,
        private readonly SetCurrentStep $setCurrentStep,
        private readonly GetCompleteness $getCompleteness,
    ) {
    }

    #[Route(name: 'state', methods: ['GET'])]
    public function state(string $profileId): JsonResponse
    {
        $result = ($this->getWizardState)($profileId);
        return new JsonResponse([
            'current_step' => $result->currentStep,
            'status' => $result->status,
            'session_id' => $result->sessionId,
            'steps' => $result->steps,
        ]);
    }

    #[Route(path: '/step', name: 'step_submit', methods: ['PUT', 'POST'])]
    public function submitStep(string $profileId, Request $request): JsonResponse
    {
        try {
            $body = $request->toArray();
            $req = new SubmitStepRequest(
                (string) ($body['step_key'] ?? ''),
                (string) ($body['status'] ?? 'pending'),
                isset($body['payload']) ? (array) $body['payload'] : null,
            );
            $result = ($this->submitStep)($profileId, $req);
            return new JsonResponse([
                'current_step' => $result->currentStep,
                'status' => $result->sessionStatus,
                'steps' => $result->steps,
            ]);
        } catch (StepValidationException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'step_key' => $e->stepKey,
                'steps' => $e->steps,
            ], 422);
        }
    }

    #[Route(path: '/current-step', name: 'current_step', methods: ['PUT'])]
    public function setCurrentStep(string $profileId, Request $request): JsonResponse
    {
        $body = $request->toArray();
        $stepKey = (string) ($body['current_step'] ?? '');
        $result = ($this->setCurrentStep)($profileId, $stepKey);
        return new JsonResponse([
            'current_step' => $result->currentStep,
            'status' => $result->status,
            'session_id' => $result->sessionId,
            'steps' => $result->steps,
        ]);
    }

    #[Route(path: '/completeness', name: 'completeness', methods: ['GET'])]
    public function completeness(string $profileId): JsonResponse
    {
        $result = ($this->getCompleteness)($profileId);
        return new JsonResponse([
            'percent' => $result->percent,
            'session_status' => $result->sessionStatus,
            'steps' => $result->steps,
        ]);
    }
}

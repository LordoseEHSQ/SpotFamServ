<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application\Port;

use App\Module\SetupWizard\Domain\ProfileSetupSession;
use App\Module\SetupWizard\Domain\ProfileSetupStepStatus;

interface ProfileSetupSessionRepositoryInterface
{
    public function findByProfileId(string $profileId): ?ProfileSetupSession;

    public function findOrCreateSession(string $profileId): ProfileSetupSession;

    /**
     * @return array<int, array{step_key: string, status: string, payload: array|null}>
     */
    public function getStepStatuses(string $sessionId): array;

    public function getStepStatus(string $sessionId, string $stepKey): ?ProfileSetupStepStatus;

    public function upsertStepStatus(string $sessionId, string $stepKey, string $status, ?array $payload = null): ProfileSetupStepStatus;

    public function save(ProfileSetupSession $session): void;
}

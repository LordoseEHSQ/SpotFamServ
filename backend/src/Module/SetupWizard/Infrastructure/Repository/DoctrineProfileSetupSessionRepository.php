<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Infrastructure\Repository;

use App\Module\SetupWizard\Application\Port\ProfileSetupSessionRepositoryInterface;
use App\Module\SetupWizard\Domain\ProfileSetupSession;
use App\Module\SetupWizard\Domain\ProfileSetupStepStatus;
use App\Module\SetupWizard\Domain\WizardSteps;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineProfileSetupSessionRepository implements ProfileSetupSessionRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findByProfileId(string $profileId): ?ProfileSetupSession
    {
        return $this->em->getRepository(ProfileSetupSession::class)->findOneBy(['familyProfileId' => $profileId]);
    }

    /**
     * Get or create session and ensure all step status rows exist (pending).
     */
    public function findOrCreateSession(string $profileId): ProfileSetupSession
    {
        $session = $this->findByProfileId($profileId);
        if ($session !== null) {
            return $session;
        }
        $session = new ProfileSetupSession($profileId);
        $this->em->persist($session);
        $this->em->flush();
        foreach (WizardSteps::ALL as $stepKey) {
            $stepStatus = new ProfileSetupStepStatus($session->getId(), $stepKey);
            $this->em->persist($stepStatus);
        }
        $this->em->flush();
        return $session;
    }

    /**
     * @return array<int, array{step_key: string, status: string, payload: array|null}>
     */
    public function getStepStatuses(string $sessionId): array
    {
        $rows = $this->em->getRepository(ProfileSetupStepStatus::class)->findBy(
            ['profileSetupSessionId' => $sessionId],
            ['stepKey' => 'ASC'],
        );
        return array_map(fn (ProfileSetupStepStatus $s) => [
            'step_key' => $s->getStepKey(),
            'status' => $s->getStatus(),
            'payload' => $s->getPayload(),
        ], $rows);
    }

    public function getStepStatus(string $sessionId, string $stepKey): ?ProfileSetupStepStatus
    {
        return $this->em->getRepository(ProfileSetupStepStatus::class)->findOneBy([
            'profileSetupSessionId' => $sessionId,
            'stepKey' => $stepKey,
        ]);
    }

    public function upsertStepStatus(string $sessionId, string $stepKey, string $status, ?array $payload = null): ProfileSetupStepStatus
    {
        $step = $this->getStepStatus($sessionId, $stepKey);
        if ($step === null) {
            $step = new ProfileSetupStepStatus($sessionId, $stepKey);
            $this->em->persist($step);
        }
        $step->setStatus($status);
        $step->setPayload($payload);
        $this->em->flush();
        return $step;
    }

    public function save(ProfileSetupSession $session): void
    {
        $this->em->persist($session);
        $this->em->flush();
    }
}

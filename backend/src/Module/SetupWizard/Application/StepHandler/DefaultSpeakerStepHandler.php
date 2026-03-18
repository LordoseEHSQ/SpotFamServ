<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application\StepHandler;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\SetupWizard\Domain\WizardSteps;
use App\Shared\Application\Exception\NotFoundException;

/**
 * Handles the default_speaker step: persists chosen device_id on the profile.
 */
final readonly class DefaultSpeakerStepHandler implements StepHandlerInterface
{
    public function __construct(
        private FamilyProfileRepositoryInterface $profileRepository,
    ) {
    }

    public function supports(string $stepKey): bool
    {
        return $stepKey === WizardSteps::STEP_DEFAULT_SPEAKER;
    }

    public function handle(string $profileId, string $stepKey, array $payload): void
    {
        $deviceId = (string) ($payload['device_id'] ?? '');
        if ($deviceId === '') {
            throw new \InvalidArgumentException('device_id is required.');
        }
        $profile = $this->profileRepository->find($profileId);
        if ($profile === null) {
            throw new NotFoundException('Profile not found.');
        }
        $profile->setDefaultSpotifyDeviceId($deviceId);
        $this->profileRepository->save($profile);
    }
}

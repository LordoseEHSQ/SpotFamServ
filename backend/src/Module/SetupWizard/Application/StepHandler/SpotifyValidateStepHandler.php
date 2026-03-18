<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application\StepHandler;

use App\Module\Spotify\Application\ValidateSpotifyConnection;
use App\Module\SetupWizard\Domain\WizardSteps;

/**
 * Handles the spotify_validate step: verifies Spotify connection is valid.
 */
final readonly class SpotifyValidateStepHandler implements StepHandlerInterface
{
    public function __construct(
        private ValidateSpotifyConnection $validateSpotifyConnection,
    ) {
    }

    public function supports(string $stepKey): bool
    {
        return $stepKey === WizardSteps::STEP_SPOTIFY_VALIDATE;
    }

    public function handle(string $profileId, string $stepKey, array $payload): void
    {
        ($this->validateSpotifyConnection)($profileId);
    }
}

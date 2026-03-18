<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application\StepHandler;

use App\Module\Spotify\Application\StartPlayback;
use App\Module\SetupWizard\Domain\WizardSteps;

/**
 * Handles the playback_test step: starts Spotify playback with a test playlist.
 */
final readonly class PlaybackTestStepHandler implements StepHandlerInterface
{
    private const DEFAULT_TEST_PLAYLIST = 'spotify:playlist:37i9dQZF1DXcBWIGoYBM5M';

    public function __construct(
        private StartPlayback $startPlayback,
    ) {
    }

    public function supports(string $stepKey): bool
    {
        return $stepKey === WizardSteps::STEP_PLAYBACK_TEST;
    }

    public function handle(string $profileId, string $stepKey, array $payload): void
    {
        $contextUri = (string) ($payload['context_uri'] ?? self::DEFAULT_TEST_PLAYLIST);
        ($this->startPlayback)($profileId, $contextUri, null);
    }
}

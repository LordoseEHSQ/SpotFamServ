<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Domain;

/**
 * Ordered wizard step keys. Used for navigation and completeness.
 */
final class WizardSteps
{
    public const STEP_PROFILE = 'profile';
    public const STEP_SPOTIFY_CONNECT = 'spotify_connect';
    public const STEP_SPOTIFY_VALIDATE = 'spotify_validate';
    public const STEP_DEVICES = 'devices';
    public const STEP_DEFAULT_SPEAKER = 'default_speaker';
    public const STEP_PLAYBACK_TEST = 'playback_test';
    public const STEP_PLAYLIST = 'playlist';
    public const STEP_RFID_BIND = 'rfid_bind';
    public const STEP_SUMMARY = 'summary';

    /** @var list<string> */
    public const ALL = [
        self::STEP_PROFILE,
        self::STEP_SPOTIFY_CONNECT,
        self::STEP_SPOTIFY_VALIDATE,
        self::STEP_DEVICES,
        self::STEP_DEFAULT_SPEAKER,
        self::STEP_PLAYBACK_TEST,
        self::STEP_PLAYLIST,
        self::STEP_RFID_BIND,
        self::STEP_SUMMARY,
    ];

    public static function indexOf(string $stepKey): int
    {
        $i = array_search($stepKey, self::ALL, true);
        return $i !== false ? $i : -1;
    }

    public static function nextStep(string $stepKey): ?string
    {
        $i = self::indexOf($stepKey);
        if ($i < 0 || $i >= \count(self::ALL) - 1) {
            return null;
        }
        return self::ALL[$i + 1];
    }

    public static function previousStep(string $stepKey): ?string
    {
        $i = self::indexOf($stepKey);
        if ($i <= 0) {
            return null;
        }
        return self::ALL[$i - 1];
    }
}

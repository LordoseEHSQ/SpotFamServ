<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use App\Module\Scan\Application\Port\PlaybackSessionStoreInterface;
use App\Module\Scan\Domain\ScanOutcome;
use App\Module\Spotify\Application\AdjustVolume;
use App\Module\Spotify\Application\PausePlayback;
use App\Module\Spotify\Application\SkipToNext;
use App\Module\Spotify\Application\SkipToPrevious;
use App\Module\Spotify\Domain\Exception\SpotifyNoDeviceException;
use App\Module\Spotify\Domain\Exception\SpotifyNotConnectedException;
use App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException;

/**
 * Handles physical reader control buttons (next/previous/pause/volume) by acting
 * on the profile of the current playback session (see PlaybackSessionStoreInterface).
 */
final readonly class ProcessReaderControl
{
    public const ACTION_NEXT = 'next';
    public const ACTION_PREVIOUS = 'previous';
    public const ACTION_PAUSE = 'pause';
    public const ACTION_VOLUME_UP = 'volume-up';
    public const ACTION_VOLUME_DOWN = 'volume-down';

    private const VOLUME_STEP = 10;

    public function __construct(
        private PlaybackSessionStoreInterface $sessionStore,
        private SkipToNext $skipToNext,
        private SkipToPrevious $skipToPrevious,
        private PausePlayback $pausePlayback,
        private AdjustVolume $adjustVolume,
    ) {
    }

    public function __invoke(string $readerId, string $action): ProcessScanResult
    {
        $allowed = [
            self::ACTION_NEXT,
            self::ACTION_PREVIOUS,
            self::ACTION_PAUSE,
            self::ACTION_VOLUME_UP,
            self::ACTION_VOLUME_DOWN,
        ];
        if (!in_array($action, $allowed, true)) {
            return new ProcessScanResult(ScanOutcome::INVALID_REQUEST, 'Unknown control action.');
        }

        $profileId = $this->sessionStore->currentProfileId($readerId);
        if ($profileId === null) {
            return new ProcessScanResult(
                ScanOutcome::NO_SESSION,
                'No active playback session. Scan a card first.',
            );
        }

        try {
            match ($action) {
                self::ACTION_NEXT        => ($this->skipToNext)($profileId, null),
                self::ACTION_PREVIOUS    => ($this->skipToPrevious)($profileId, null),
                self::ACTION_PAUSE       => ($this->pausePlayback)($profileId, null),
                self::ACTION_VOLUME_UP   => ($this->adjustVolume)($profileId, +self::VOLUME_STEP),
                self::ACTION_VOLUME_DOWN => ($this->adjustVolume)($profileId, -self::VOLUME_STEP),
            };
        } catch (SpotifyNoDeviceException $e) {
            return new ProcessScanResult(ScanOutcome::NO_DEVICE, $e->getMessage());
        } catch (SpotifyNotConnectedException | SpotifyTokenInvalidException $e) {
            return new ProcessScanResult(ScanOutcome::TOKEN_INVALID, $e->getMessage());
        } catch (\Throwable $e) {
            return new ProcessScanResult(ScanOutcome::PLAYBACK_FAILED, $e->getMessage());
        }

        return new ProcessScanResult(ScanOutcome::SUCCESS, ucfirst(str_replace('-', ' ', $action)) . ' ok.');
    }
}

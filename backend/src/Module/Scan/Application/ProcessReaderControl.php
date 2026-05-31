<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use App\Module\Scan\Application\Port\PlaybackSessionStoreInterface;
use App\Module\Scan\Domain\ScanOutcome;
use App\Module\Spotify\Application\SkipToNext;
use App\Module\Spotify\Application\SkipToPrevious;
use App\Module\Spotify\Domain\Exception\SpotifyNoDeviceException;
use App\Module\Spotify\Domain\Exception\SpotifyNotConnectedException;
use App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException;

/**
 * Handles physical reader control buttons (next/previous) by acting on the
 * profile of the current playback session (see PlaybackSessionStoreInterface).
 */
final readonly class ProcessReaderControl
{
    public const ACTION_NEXT = 'next';
    public const ACTION_PREVIOUS = 'previous';

    public function __construct(
        private PlaybackSessionStoreInterface $sessionStore,
        private SkipToNext $skipToNext,
        private SkipToPrevious $skipToPrevious,
    ) {
    }

    public function __invoke(string $readerId, string $action): ProcessScanResult
    {
        if ($action !== self::ACTION_NEXT && $action !== self::ACTION_PREVIOUS) {
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
            if ($action === self::ACTION_NEXT) {
                ($this->skipToNext)($profileId, null);
            } else {
                ($this->skipToPrevious)($profileId, null);
            }
        } catch (SpotifyNoDeviceException $e) {
            return new ProcessScanResult(ScanOutcome::NO_DEVICE, $e->getMessage());
        } catch (SpotifyNotConnectedException | SpotifyTokenInvalidException $e) {
            return new ProcessScanResult(ScanOutcome::TOKEN_INVALID, $e->getMessage());
        } catch (\Throwable $e) {
            return new ProcessScanResult(ScanOutcome::PLAYBACK_FAILED, $e->getMessage());
        }

        return new ProcessScanResult(ScanOutcome::SUCCESS, ucfirst($action) . ' ok.');
    }
}

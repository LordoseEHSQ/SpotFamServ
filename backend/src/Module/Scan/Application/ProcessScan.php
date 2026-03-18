<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Application\Port\ScanCardResolverInterface;
use App\Module\Scan\Application\Port\ScanEventRepositoryInterface;
use App\Module\Scan\Domain\ScanOutcome;
use App\Module\Spotify\Application\StartPlayback;
use App\Module\Spotify\Domain\Exception\SpotifyNoDeviceException;
use App\Module\Spotify\Domain\Exception\SpotifyNotConnectedException;
use App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException;

/**
 * Processes a reader scan: resolve card → profile → binding → default speaker → start playback.
 * Logs every scan (success or failure). Debounces duplicate scans within a short time.
 */
final readonly class ProcessScan
{
    private const DEBOUNCE_SECONDS = 5;

    public function __construct(
        private ScanEventRepositoryInterface $scanEventRepository,
        private ScanCardResolverInterface $cardResolver,
        private ReaderDeviceRepositoryInterface $readerDeviceRepository,
        private StartPlayback $startPlayback,
    ) {
    }

    public function __invoke(string $readerId, string $cardUid): ProcessScanResult
    {
        $cardUid = trim($cardUid);
        if ($cardUid === '') {
            $this->logScan('', ScanOutcome::INVALID_REQUEST, $readerId, null, null, null, ['error' => 'Missing card_uid']);
            return new ProcessScanResult(ScanOutcome::INVALID_REQUEST, 'Missing card_uid.');
        }

        $readerDeviceId = $readerId !== ''
            ? $this->readerDeviceRepository->findByReaderId($readerId)?->getId()
            : null;

        if ($this->scanEventRepository->findRecentScan($cardUid, self::DEBOUNCE_SECONDS, $readerId) !== null) {
            $this->logScan($cardUid, ScanOutcome::DEBOUNCED, $readerId, $readerDeviceId, null, null, ['debounce_seconds' => self::DEBOUNCE_SECONDS]);
            return new ProcessScanResult(ScanOutcome::DEBOUNCED, 'Duplicate scan ignored.');
        }

        $context = $this->cardResolver->resolveCard($cardUid);

        if ($context === null) {
            $this->logScan($cardUid, ScanOutcome::UNKNOWN_CARD, $readerId, $readerDeviceId, null, null);
            return new ProcessScanResult(ScanOutcome::UNKNOWN_CARD, 'Card not registered or has no playlist binding.');
        }

        $profileId = $context->profileId;
        $cardId = $context->cardId;

        try {
            ($this->startPlayback)($profileId, $context->playlistUri, null);
        } catch (SpotifyNoDeviceException $e) {
            $this->logScan($cardUid, ScanOutcome::NO_DEVICE, $readerId, $readerDeviceId, $cardId, $profileId, ['error' => $e->getMessage()]);
            return new ProcessScanResult(ScanOutcome::NO_DEVICE, $e->getMessage());
        } catch (SpotifyNotConnectedException $e) {
            $this->logScan($cardUid, ScanOutcome::TOKEN_INVALID, $readerId, $readerDeviceId, $cardId, $profileId, ['error' => $e->getMessage()]);
            return new ProcessScanResult(ScanOutcome::TOKEN_INVALID, $e->getMessage());
        } catch (SpotifyTokenInvalidException $e) {
            $this->logScan($cardUid, ScanOutcome::TOKEN_INVALID, $readerId, $readerDeviceId, $cardId, $profileId, ['error' => $e->getMessage()]);
            return new ProcessScanResult(ScanOutcome::TOKEN_INVALID, $e->getMessage());
        } catch (\Throwable $e) {
            $this->logScan($cardUid, ScanOutcome::PLAYBACK_FAILED, $readerId, $readerDeviceId, $cardId, $profileId, ['error' => $e->getMessage()]);
            return new ProcessScanResult(ScanOutcome::PLAYBACK_FAILED, $e->getMessage());
        }

        $this->logScan($cardUid, ScanOutcome::SUCCESS, $readerId, $readerDeviceId, $cardId, $profileId, ['context_uri' => $context->playlistUri]);
        return new ProcessScanResult(ScanOutcome::SUCCESS, 'Playback started.');
    }

    private function logScan(
        string $cardUid,
        string $outcome,
        ?string $readerId,
        ?string $readerDeviceId,
        ?string $cardId,
        ?string $profileId,
        array $details = [],
    ): void {
        $this->scanEventRepository->append(
            $cardUid,
            $outcome,
            $readerId,
            $profileId,
            $details !== [] ? $details : null,
            $readerDeviceId,
            $cardId,
            $profileId,
        );
    }
}

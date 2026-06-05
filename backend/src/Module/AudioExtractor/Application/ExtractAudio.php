<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Application\Port\AudioStorageInterface;
use App\Module\AudioExtractor\Application\Port\MediaExtractorInterface;
use App\Module\AudioExtractor\Domain\AudioFormat;
use App\Module\AudioExtractor\Domain\ExtractorBusyException;
use App\Module\AudioExtractor\Domain\InvalidMediaRequestException;
use App\Module\AudioExtractor\Domain\StorageQuotaExceededException;
use App\Module\AudioExtractor\Domain\StoredAudioFile;
use Symfony\Component\Lock\LockFactory;

/**
 * Validates the request (scheme, format, bitrate) BEFORE any subprocess runs, then –
 * under an exclusive engine lock – extracts the audio to a temp file and persists it
 * into the shared user storage area.
 *
 * Two non-trivial guards (Sprint 07 / D-033, D-034):
 *  - Concurrency / update-race lock: only one extraction OR engine self-update may run
 *    at a time. A second concurrent request fails fast with {@see ExtractorBusyException}
 *    (409) instead of racing yt-dlp against `yt-dlp -U` or saturating the host.
 *  - Storage quota: enforced before extraction (current total) and after storing (the
 *    produced file would push the area over the limit → it is deleted again, 507). The
 *    final size is only known after the download, so a request-time check is not enough.
 */
final readonly class ExtractAudio
{
    /** @var list<int> */
    public const ALLOWED_BITRATES_KBPS = [128, 192, 256, 320];

    public const DEFAULT_BITRATE_KBPS = 192;

    /** Shared lock key – MUST match {@see UpdateEngine} so extraction and `-U` exclude each other. */
    public const ENGINE_LOCK_KEY = 'audio-extractor:engine';

    private const ALLOWED_SCHEMES = ['http', 'https'];

    public function __construct(
        private MediaExtractorInterface $extractor,
        private AudioStorageInterface $storage,
        private LockFactory $lockFactory,
        private int $maxTotalBytes,
        private int $lockTtlSeconds = 3600,
    ) {
    }

    public function __invoke(string $url, string $formatValue, ?int $bitrateKbps): StoredAudioFile
    {
        $url = trim($url);
        if ($url === '') {
            throw new InvalidMediaRequestException('Missing URL.');
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            throw new InvalidMediaRequestException('URL could not be parsed.');
        }
        if (!in_array(strtolower($parts['scheme']), self::ALLOWED_SCHEMES, true)) {
            throw new InvalidMediaRequestException('Only http(s) URLs are allowed.');
        }

        $format = AudioFormat::tryFromValue($formatValue);
        if ($format === null) {
            throw new InvalidMediaRequestException(sprintf(
                'Unsupported format "%s". Allowed: %s.',
                $formatValue,
                implode(', ', AudioFormat::values()),
            ));
        }

        $effectiveBitrate = null;
        if ($format->supportsBitrate()) {
            $effectiveBitrate = $bitrateKbps ?? self::DEFAULT_BITRATE_KBPS;
            if (!in_array($effectiveBitrate, self::ALLOWED_BITRATES_KBPS, true)) {
                throw new InvalidMediaRequestException(sprintf(
                    'Unsupported bitrate "%d". Allowed: %s.',
                    $effectiveBitrate,
                    implode(', ', self::ALLOWED_BITRATES_KBPS),
                ));
            }
        }

        // Acquire the exclusive engine lock (non-blocking): one extraction/update at a time.
        $lock = $this->lockFactory->createLock(self::ENGINE_LOCK_KEY, (float) $this->lockTtlSeconds);
        if (!$lock->acquire()) {
            throw new ExtractorBusyException(
                'Another extraction or engine update is already running. Please try again shortly.',
            );
        }

        try {
            // Pre-check: refuse to even start a download when the area is already full.
            if ($this->storage->totalSizeBytes() >= $this->maxTotalBytes) {
                throw new StorageQuotaExceededException($this->quotaMessage());
            }

            $extracted = $this->extractor->extract($url, $format, $effectiveBitrate);
            $stored = $this->storage->store($extracted->filePath, $extracted->downloadName);

            // Post-check: the produced file size is only known now. If it pushed the area
            // over the limit, roll back (delete) and report – never leave the quota breached.
            if ($this->storage->totalSizeBytes() > $this->maxTotalBytes) {
                $this->storage->delete($stored->name);
                throw new StorageQuotaExceededException($this->quotaMessage());
            }

            return $stored;
        } finally {
            $lock->release();
        }
    }

    private function quotaMessage(): string
    {
        return sprintf(
            'Storage quota of %d MB is exhausted. Delete some files and try again.',
            intdiv($this->maxTotalBytes, 1024 * 1024),
        );
    }
}

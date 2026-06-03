<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Application\Port\AudioStorageInterface;
use App\Module\AudioExtractor\Application\Port\MediaExtractorInterface;
use App\Module\AudioExtractor\Domain\AudioFormat;
use App\Module\AudioExtractor\Domain\InvalidMediaRequestException;
use App\Module\AudioExtractor\Domain\StoredAudioFile;

/**
 * Validates the request (scheme, format, bitrate) BEFORE any subprocess runs, extracts
 * the audio to a temp file, then persists it into the shared user storage area. All
 * untrusted input is checked here so the adapter only ever receives a known-good URL.
 */
final readonly class ExtractAudio
{
    /** @var list<int> */
    public const ALLOWED_BITRATES_KBPS = [128, 192, 256, 320];

    public const DEFAULT_BITRATE_KBPS = 192;

    private const ALLOWED_SCHEMES = ['http', 'https'];

    public function __construct(
        private MediaExtractorInterface $extractor,
        private AudioStorageInterface $storage,
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

        $extracted = $this->extractor->extract($url, $format, $effectiveBitrate);

        return $this->storage->store($extracted->filePath, $extracted->downloadName);
    }
}

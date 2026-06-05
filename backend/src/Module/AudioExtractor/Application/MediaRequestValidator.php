<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Domain\AudioFormat;
use App\Module\AudioExtractor\Domain\InvalidMediaRequestException;
use App\Module\AudioExtractor\Domain\MediaRequest;

/**
 * Single source of request validation (the security boundary). Used synchronously when a
 * job is created – so bad input is rejected with 422 BEFORE a job/202 is produced – and
 * again inside {@see ExtractAudio} as defense in depth before any subprocess runs.
 */
final class MediaRequestValidator
{
    /** @var list<int> */
    public const ALLOWED_BITRATES_KBPS = [128, 192, 256, 320];

    public const DEFAULT_BITRATE_KBPS = 192;

    private const ALLOWED_SCHEMES = ['http', 'https'];

    public function validate(string $url, string $formatValue, ?int $bitrateKbps): MediaRequest
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

        return new MediaRequest($url, $format, $effectiveBitrate);
    }
}

<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Domain;

/**
 * A validated, normalised extraction request: a safe http(s) URL, a known output format
 * and (for lossy formats) an allowed bitrate. Constructed only via the validator, so any
 * instance is guaranteed to be safe to hand to the subprocess adapter.
 */
final readonly class MediaRequest
{
    public function __construct(
        public string $url,
        public AudioFormat $format,
        public ?int $bitrateKbps,
    ) {
    }
}

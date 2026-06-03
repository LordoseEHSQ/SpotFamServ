<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application\Port;

use App\Module\AudioExtractor\Domain\AudioFormat;
use App\Module\AudioExtractor\Domain\ExtractedAudio;
use App\Module\AudioExtractor\Domain\MediaExtractionFailedException;

/**
 * Port for turning a (validated) media URL into a local audio file. The adapter
 * (yt-dlp + ffmpeg) is the only place that touches subprocesses, so the use case
 * stays mockable in unit tests.
 */
interface MediaExtractorInterface
{
    /**
     * @param string         $url           validated http(s) URL
     * @param AudioFormat    $format        target container/codec
     * @param int|null       $bitrateKbps   only used for formats that support it (MP3)
     *
     * @throws MediaExtractionFailedException
     */
    public function extract(string $url, AudioFormat $format, ?int $bitrateKbps): ExtractedAudio;
}

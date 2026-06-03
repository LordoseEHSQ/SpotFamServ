<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Domain;

/**
 * Upstream/tooling error (HTTP 502): yt-dlp/ffmpeg failed, timed out, the source was
 * unavailable, or no audio file was produced (e.g. duration guard filtered it out).
 */
final class MediaExtractionFailedException extends \RuntimeException
{
}

<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Domain;

/**
 * Concurrency guard (HTTP 409): another extraction or an engine self-update
 * (`yt-dlp -U`) is already running. Only one operation may touch the engine at a
 * time – this prevents a self-update from replacing the binary mid-extraction and
 * limits concurrent extractions to one.
 */
final class ExtractorBusyException extends \RuntimeException
{
}

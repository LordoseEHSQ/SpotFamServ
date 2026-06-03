<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application\Port;

use App\Module\AudioExtractor\Domain\MediaExtractionFailedException;

/**
 * Lifecycle of the extraction engine (yt-dlp): report version and self-update.
 * Separated from extraction so it can be exercised/mocked independently.
 */
interface MediaEngineInterface
{
    /** Current engine version, or null if it cannot be determined. */
    public function version(): ?string;

    /**
     * Updates the engine in place (yt-dlp -U) and returns the resulting version.
     *
     * @throws MediaExtractionFailedException on update failure
     */
    public function update(): string;
}

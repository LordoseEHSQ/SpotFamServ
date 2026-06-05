<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application\Message;

/**
 * Dispatched when an {@see \App\Module\AudioExtractor\Domain\AudioJob} is created. Carries
 * only the job id; the handler reloads the job and reads url/format/bitrate from it, so the
 * persisted job stays the single source of truth (no duplicated payload to drift).
 */
final readonly class ExtractAudioMessage
{
    public function __construct(
        public string $jobId,
    ) {
    }
}

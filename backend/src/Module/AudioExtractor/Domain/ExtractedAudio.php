<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Domain;

/**
 * Result of an extraction: a transient file on local disk plus the metadata needed
 * to stream it back as a download. No persistence (Plan D-C); the caller is
 * responsible for deleting the file after sending it.
 */
final readonly class ExtractedAudio
{
    public function __construct(
        public string $filePath,
        public string $downloadName,
        public string $mimeType,
    ) {
    }
}

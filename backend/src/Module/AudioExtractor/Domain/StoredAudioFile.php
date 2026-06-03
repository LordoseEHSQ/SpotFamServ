<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Domain;

/**
 * A persisted audio file in the user storage area. Identified by its (sanitised,
 * ASCII-restricted) file name; there is no database row (Plan: filesystem-scan,
 * shared area, no schema).
 */
final readonly class StoredAudioFile
{
    public function __construct(
        public string $name,
        public int $sizeBytes,
        public \DateTimeImmutable $createdAt,
        public string $mimeType,
    ) {
    }
}

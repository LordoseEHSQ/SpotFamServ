<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application\Port;

use App\Module\AudioExtractor\Domain\StoredAudioFile;

/**
 * Persistent storage for extracted audio in the shared user area. Implementations
 * MUST guard against path traversal: a file name is never a path.
 */
interface AudioStorageInterface
{
    /**
     * Moves a freshly extracted temp file into the storage area, resolving name
     * collisions, and returns the stored file descriptor.
     */
    public function store(string $sourcePath, string $desiredName): StoredAudioFile;

    /** @return list<StoredAudioFile> newest first */
    public function list(): array;

    /**
     * Total bytes used by the storage area (for usage display; there is no hard quota).
     */
    public function totalSizeBytes(): int;

    /**
     * Absolute path of a stored file, validated to live inside the storage area.
     * Returns null if the name is invalid or the file does not exist.
     */
    public function absolutePath(string $name): ?string;

    /**
     * @return bool true if a file was deleted, false if it did not exist / name invalid
     */
    public function delete(string $name): bool;
}

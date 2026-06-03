<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Infrastructure\Storage;

use App\Module\AudioExtractor\Application\Port\AudioStorageInterface;
use App\Module\AudioExtractor\Domain\AudioFormat;
use App\Module\AudioExtractor\Domain\MediaExtractionFailedException;
use App\Module\AudioExtractor\Domain\StoredAudioFile;

/**
 * Stores extracted audio in a single shared directory on disk (host-mounted user
 * area). No database; listing is a directory scan.
 *
 * Path-traversal defense: a file name is treated as an opaque token, never a path.
 * Every public lookup runs the name through {@see sanitizeName()} (which rejects
 * separators, dots-only, and empty results) and then verifies the resolved real path
 * still sits inside the storage directory.
 */
final class FilesystemAudioStorage implements AudioStorageInterface
{
    public function __construct(
        private readonly string $storageDir,
    ) {
    }

    public function store(string $sourcePath, string $desiredName): StoredAudioFile
    {
        if (!is_file($sourcePath)) {
            throw new MediaExtractionFailedException('Extracted file vanished before storage.');
        }

        $this->ensureDir();

        $name = $this->sanitizeName($desiredName);
        if ($name === null) {
            $name = 'audio.' . AudioFormat::Mp3->extension();
        }

        $target = $this->resolveCollision($name);
        $absolute = $this->storageRealDir() . \DIRECTORY_SEPARATOR . $target;

        if (!@rename($sourcePath, $absolute)) {
            // rename fails across filesystems/mount boundaries → fall back to copy+unlink.
            if (!@copy($sourcePath, $absolute)) {
                @unlink($sourcePath);
                throw new MediaExtractionFailedException('Could not persist the extracted file.');
            }
            @unlink($sourcePath);
        }

        return $this->describe($absolute, $target);
    }

    public function list(): array
    {
        $dir = $this->storageRealDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach ((array) scandir($dir) as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }
            $absolute = $dir . \DIRECTORY_SEPARATOR . $entry;
            if (!is_file($absolute) || !$this->isKnownAudio($entry)) {
                continue;
            }
            $files[] = $this->describe($absolute, $entry);
        }

        usort($files, static fn (StoredAudioFile $a, StoredAudioFile $b): int => $b->createdAt <=> $a->createdAt);

        return $files;
    }

    public function totalSizeBytes(): int
    {
        $total = 0;
        foreach ($this->list() as $file) {
            $total += $file->sizeBytes;
        }

        return $total;
    }

    public function absolutePath(string $name): ?string
    {
        $clean = $this->sanitizeName($name);
        if ($clean === null) {
            return null;
        }

        $dir = $this->storageRealDir();
        $candidate = $dir . \DIRECTORY_SEPARATOR . $clean;
        $real = realpath($candidate);

        // Must resolve to a regular file that is a direct child of the storage dir.
        if ($real === false || !is_file($real)) {
            return null;
        }
        if (\dirname($real) !== $dir) {
            return null;
        }

        return $real;
    }

    public function delete(string $name): bool
    {
        $path = $this->absolutePath($name);
        if ($path === null) {
            return false;
        }

        return @unlink($path);
    }

    private function describe(string $absolutePath, string $name): StoredAudioFile
    {
        $size = filesize($absolutePath);
        $mtime = filemtime($absolutePath);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = AudioFormat::tryFrom($ext)?->mimeType() ?? 'application/octet-stream';

        return new StoredAudioFile(
            name: $name,
            sizeBytes: $size === false ? 0 : $size,
            createdAt: (new \DateTimeImmutable())->setTimestamp($mtime === false ? time() : $mtime),
            mimeType: $mime,
        );
    }

    /**
     * Reduces an arbitrary name to a safe single path segment, or null if nothing
     * usable remains. Rejects path separators outright (no silent basename of a path).
     */
    private function sanitizeName(string $name): ?string
    {
        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            return null;
        }
        if ($name === '.' || $name === '..' || str_contains($name, '..')) {
            return null;
        }

        // Restrict to a conservative, URL- and FS-safe charset.
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? '';
        $clean = ltrim($clean, '.');

        return $clean !== '' ? $clean : null;
    }

    private function resolveCollision(string $name): string
    {
        $dir = $this->storageRealDir();
        if (!file_exists($dir . \DIRECTORY_SEPARATOR . $name)) {
            return $name;
        }

        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $stem = pathinfo($name, PATHINFO_FILENAME);
        $suffix = $ext !== '' ? '.' . $ext : '';

        for ($i = 2; $i < 1000; $i++) {
            $candidate = $stem . '_' . $i . $suffix;
            if (!file_exists($dir . \DIRECTORY_SEPARATOR . $candidate)) {
                return $candidate;
            }
        }

        // Extremely unlikely; fall back to a unique token.
        return $stem . '_' . bin2hex(random_bytes(4)) . $suffix;
    }

    private function isKnownAudio(string $name): bool
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return AudioFormat::tryFrom($ext) !== null;
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0o775, true) && !is_dir($this->storageDir)) {
            throw new MediaExtractionFailedException('Storage directory could not be created.');
        }
    }

    /**
     * Canonical storage directory. Created on demand so realpath() succeeds.
     */
    private function storageRealDir(): string
    {
        $this->ensureDir();
        $real = realpath($this->storageDir);

        return $real === false ? rtrim($this->storageDir, \DIRECTORY_SEPARATOR) : $real;
    }
}

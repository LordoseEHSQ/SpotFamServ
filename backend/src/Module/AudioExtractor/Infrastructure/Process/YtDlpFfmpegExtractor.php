<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Infrastructure\Process;

use App\Module\AudioExtractor\Application\Port\MediaEngineInterface;
use App\Module\AudioExtractor\Application\Port\MediaExtractorInterface;
use App\Module\AudioExtractor\Domain\AudioFormat;
use App\Module\AudioExtractor\Domain\ExtractedAudio;
use App\Module\AudioExtractor\Domain\MediaExtractionFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Runs yt-dlp (audio extraction) + ffmpeg (transcode) as a single subprocess, and
 * manages the engine lifecycle (version / self-update).
 *
 * Security: the command is built as an argument array (never a shell string), so a
 * malicious URL cannot inject extra arguments or shell metacharacters. The URL is
 * always the final, single positional argument.
 *
 * Robustness: a per-job timeout and a duration guard prevent a runaway/huge download
 * from blocking the php-fpm worker (Plan R1). Output goes to a uniquely named file in
 * the system temp dir; the caller moves it into persistent storage.
 */
final class YtDlpFfmpegExtractor implements MediaExtractorInterface, MediaEngineInterface
{
    public function __construct(
        private readonly string $ytDlpBinary = 'yt-dlp',
        private readonly int $timeoutSeconds = 240,
        private readonly int $maxDurationSeconds = 1800,
        private readonly ?string $tempDir = null,
    ) {
    }

    public function extract(string $url, AudioFormat $format, ?int $bitrateKbps): ExtractedAudio
    {
        $tempDir = $this->tempDir ?? sys_get_temp_dir();
        $jobId = 'sfx_' . bin2hex(random_bytes(8));
        // yt-dlp fills %(title)s/%(ext)s; we glob for the final file by the unique prefix.
        $outputTemplate = $tempDir . \DIRECTORY_SEPARATOR . $jobId . '_%(title).120B.%(ext)s';

        $command = [
            $this->ytDlpBinary,
            '--extract-audio',
            '--audio-format', $format->extension(),
            '--no-playlist',
            '--no-progress',
            '--no-continue',
            '--restrict-filenames',
            // Duration guard: reject sources longer than the limit, but ALLOW sources
            // whose duration is unknown (e.g. direct files via the generic extractor).
            // Multiple --match-filter are OR'd in yt-dlp; the process timeout remains the
            // hard backstop for unknown-duration long downloads.
            '--match-filter', sprintf('duration<%d', $this->maxDurationSeconds),
            '--match-filter', '!duration',
            '--output', $outputTemplate,
        ];

        if ($format->supportsBitrate() && $bitrateKbps !== null) {
            $command[] = '--audio-quality';
            $command[] = $bitrateKbps . 'K';
        }

        // URL is always the final, single positional argument.
        $command[] = $url;

        $process = new Process($command);
        $process->setTimeout((float) $this->timeoutSeconds);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $this->cleanup($tempDir, $jobId);
            throw new MediaExtractionFailedException(sprintf(
                'Extraction timed out after %d seconds.',
                $this->timeoutSeconds,
            ));
        }

        if (!$process->isSuccessful()) {
            $this->cleanup($tempDir, $jobId);
            throw new MediaExtractionFailedException($this->summariseError($process->getErrorOutput()));
        }

        $file = $this->findOutputFile($tempDir, $jobId, $format->extension());
        if ($file === null) {
            $this->cleanup($tempDir, $jobId);
            throw new MediaExtractionFailedException(
                'No audio file was produced (the source may be unavailable or longer than the allowed limit).',
            );
        }

        return new ExtractedAudio(
            filePath: $file,
            downloadName: $this->downloadName($file, $jobId, $format->extension()),
            mimeType: $format->mimeType(),
        );
    }

    public function version(): ?string
    {
        $process = new Process([$this->ytDlpBinary, '--version']);
        $process->setTimeout(30.0);

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        if (!$process->isSuccessful()) {
            return null;
        }

        $version = trim($process->getOutput());

        return $version !== '' ? $version : null;
    }

    public function update(): string
    {
        // yt-dlp -U self-updates the binary in place (needs write access to its dir).
        $process = new Process([$this->ytDlpBinary, '-U']);
        $process->setTimeout(120.0);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new MediaExtractionFailedException('Engine update timed out.');
        }

        if (!$process->isSuccessful()) {
            throw new MediaExtractionFailedException($this->summariseError($process->getErrorOutput()));
        }

        $version = $this->version();
        if ($version === null) {
            throw new MediaExtractionFailedException('Engine update finished but version could not be read.');
        }

        return $version;
    }

    private function findOutputFile(string $tempDir, string $jobId, string $extension): ?string
    {
        $matches = glob($tempDir . \DIRECTORY_SEPARATOR . $jobId . '_*.' . $extension);
        if ($matches === false || $matches === []) {
            return null;
        }

        // Deterministic pick: the unique job-id prefix already scopes the match to this
        // job, but a source may yield more than one file. Return the most recently
        // written one (newest mtime) instead of glob()'s arbitrary first entry.
        usort($matches, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        return $matches[0];
    }

    /**
     * Strips the internal job-id prefix so the user gets a clean "<title>.<ext>".
     */
    private function downloadName(string $filePath, string $jobId, string $extension): string
    {
        $base = basename($filePath);
        $prefix = $jobId . '_';
        if (str_starts_with($base, $prefix)) {
            $base = substr($base, strlen($prefix));
        }

        return $base !== '' ? $base : ('audio.' . $extension);
    }

    private function cleanup(string $tempDir, string $jobId): void
    {
        $matches = glob($tempDir . \DIRECTORY_SEPARATOR . $jobId . '_*');
        if ($matches === false) {
            return;
        }
        foreach ($matches as $leftover) {
            if (is_file($leftover)) {
                @unlink($leftover);
            }
        }
    }

    /**
     * Returns the last non-empty stderr line, truncated, to avoid leaking large or
     * sensitive output while still giving the user an actionable hint.
     */
    private function summariseError(string $stderr): string
    {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $stderr)),
            static fn (string $line): bool => $line !== '',
        ));
        $last = $lines === [] ? 'unknown error' : $lines[array_key_last($lines)];

        if (mb_strlen($last) > 300) {
            $last = mb_substr($last, 0, 300) . '…';
        }

        return 'Extraction failed: ' . $last;
    }
}

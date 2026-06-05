<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Domain;

/**
 * Supported output formats (Sprint 07 / D-035). All are produced natively by yt-dlp's
 * `--audio-format`. Lossy formats (mp3/aac/m4a/opus) carry a selectable bitrate; lossless
 * formats (wav/flac) ignore the bitrate field (it is dropped before the subprocess).
 *
 * The enum value doubles as the file extension AND the yt-dlp `--audio-format` argument,
 * so it must stay identical to what yt-dlp writes to disk (used to glob the output file).
 */
enum AudioFormat: string
{
    case Mp3 = 'mp3';
    case Wav = 'wav';
    case Opus = 'opus';
    case Flac = 'flac';
    case M4a = 'm4a';
    case Aac = 'aac';

    public function extension(): string
    {
        return $this->value;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Mp3 => 'audio/mpeg',
            self::Wav => 'audio/wav',
            self::Opus => 'audio/opus',
            self::Flac => 'audio/flac',
            self::M4a => 'audio/mp4',
            self::Aac => 'audio/aac',
        };
    }

    public function supportsBitrate(): bool
    {
        // Lossy codecs honour a target bitrate; lossless (wav/flac) do not.
        return match ($this) {
            self::Mp3, self::Opus, self::M4a, self::Aac => true,
            self::Wav, self::Flac => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $f): string => $f->value, self::cases());
    }

    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}

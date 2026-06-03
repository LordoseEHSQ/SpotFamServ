<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Domain;

/**
 * Supported output formats. MP3 carries a selectable bitrate; WAV is PCM (lossless,
 * bitrate field is ignored). Kept intentionally small (Plan D-E: lightweight).
 */
enum AudioFormat: string
{
    case Mp3 = 'mp3';
    case Wav = 'wav';

    public function extension(): string
    {
        return $this->value;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Mp3 => 'audio/mpeg',
            self::Wav => 'audio/wav',
        };
    }

    public function supportsBitrate(): bool
    {
        return $this === self::Mp3;
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

<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Domain;

use App\Module\AudioExtractor\Domain\AudioFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AudioFormatTest extends TestCase
{
    public function test_all_expected_formats_are_supported(): void
    {
        $this->assertSame(['mp3', 'wav', 'opus', 'flac', 'm4a', 'aac'], AudioFormat::values());
    }

    /**
     * @return iterable<string, array{AudioFormat, string, bool}>
     */
    public static function formats(): iterable
    {
        // format, expected mime, lossy (→ supports bitrate)
        yield 'mp3' => [AudioFormat::Mp3, 'audio/mpeg', true];
        yield 'wav' => [AudioFormat::Wav, 'audio/wav', false];
        yield 'opus' => [AudioFormat::Opus, 'audio/opus', true];
        yield 'flac' => [AudioFormat::Flac, 'audio/flac', false];
        yield 'm4a' => [AudioFormat::M4a, 'audio/mp4', true];
        yield 'aac' => [AudioFormat::Aac, 'audio/aac', true];
    }

    #[DataProvider('formats')]
    public function test_extension_mime_and_bitrate_support(AudioFormat $format, string $mime, bool $lossy): void
    {
        // The extension MUST equal the enum value (used as yt-dlp --audio-format and to glob output).
        $this->assertSame($format->value, $format->extension());
        $this->assertSame($mime, $format->mimeType());
        $this->assertSame($lossy, $format->supportsBitrate());
    }
}

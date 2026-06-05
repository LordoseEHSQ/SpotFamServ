<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Application\MediaRequestValidator;
use App\Module\AudioExtractor\Domain\AudioFormat;
use App\Module\AudioExtractor\Domain\InvalidMediaRequestException;
use PHPUnit\Framework\TestCase;

class MediaRequestValidatorTest extends TestCase
{
    private MediaRequestValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new MediaRequestValidator();
    }

    public function test_lossy_opus_keeps_explicit_bitrate(): void
    {
        $request = $this->validator->validate('https://example.com/v', 'opus', 256);

        $this->assertSame(AudioFormat::Opus, $request->format);
        $this->assertSame(256, $request->bitrateKbps);
    }

    public function test_lossy_aac_defaults_bitrate_when_missing(): void
    {
        $request = $this->validator->validate('https://example.com/v', 'aac', null);

        $this->assertSame(MediaRequestValidator::DEFAULT_BITRATE_KBPS, $request->bitrateKbps);
    }

    public function test_lossless_flac_drops_bitrate(): void
    {
        $request = $this->validator->validate('https://example.com/v', 'flac', 320);

        $this->assertSame(AudioFormat::Flac, $request->format);
        $this->assertNull($request->bitrateKbps);
    }

    public function test_m4a_is_accepted(): void
    {
        $request = $this->validator->validate('https://example.com/v', 'm4a', 192);

        $this->assertSame(AudioFormat::M4a, $request->format);
        $this->assertSame(192, $request->bitrateKbps);
    }

    public function test_unsupported_format_is_rejected(): void
    {
        $this->expectException(InvalidMediaRequestException::class);
        $this->validator->validate('https://example.com/v', 'ogg', null);
    }

    public function test_invalid_bitrate_for_lossy_format_is_rejected(): void
    {
        $this->expectException(InvalidMediaRequestException::class);
        $this->validator->validate('https://example.com/v', 'opus', 64);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Application;

use App\Module\AudioExtractor\Application\ExtractAudio;
use App\Module\AudioExtractor\Application\Port\AudioStorageInterface;
use App\Module\AudioExtractor\Application\Port\MediaExtractorInterface;
use App\Module\AudioExtractor\Domain\AudioFormat;
use App\Module\AudioExtractor\Domain\ExtractedAudio;
use App\Module\AudioExtractor\Domain\InvalidMediaRequestException;
use App\Module\AudioExtractor\Domain\StoredAudioFile;
use PHPUnit\Framework\TestCase;

/**
 * Validation is the security boundary: untrusted input must be rejected BEFORE the
 * extractor (subprocess) or storage is ever touched. These tests assert that boundary
 * and the extract → store orchestration.
 */
class ExtractAudioTest extends TestCase
{
    private function storedFile(): StoredAudioFile
    {
        return new StoredAudioFile('x.mp3', 10, new \DateTimeImmutable(), 'audio/mpeg');
    }

    private function extractorReturning(ExtractedAudio $audio): MediaExtractorInterface
    {
        $mock = $this->createMock(MediaExtractorInterface::class);
        $mock->method('extract')->willReturn($audio);

        return $mock;
    }

    private function passthroughStorage(): AudioStorageInterface
    {
        $mock = $this->createMock(AudioStorageInterface::class);
        $mock->method('store')->willReturn($this->storedFile());

        return $mock;
    }

    /** The extractor and storage must NOT be called for invalid input. */
    private function strictExtractor(): MediaExtractorInterface
    {
        $mock = $this->createMock(MediaExtractorInterface::class);
        $mock->expects($this->never())->method('extract');

        return $mock;
    }

    private function strictStorage(): AudioStorageInterface
    {
        $mock = $this->createMock(AudioStorageInterface::class);
        $mock->expects($this->never())->method('store');

        return $mock;
    }

    public function test_valid_mp3_request_extracts_then_stores(): void
    {
        $extracted = new ExtractedAudio('/tmp/job.mp3', 'song.mp3', 'audio/mpeg');

        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->expects($this->once())
            ->method('store')
            ->with('/tmp/job.mp3', 'song.mp3')
            ->willReturn($this->storedFile());

        $useCase = new ExtractAudio($this->extractorReturning($extracted), $storage);
        $result = $useCase('https://example.com/watch?v=abc', 'mp3', 256);
        $this->assertSame('audio/mpeg', $result->mimeType);
    }

    public function test_valid_wav_passes_null_bitrate_to_extractor(): void
    {
        $extractor = $this->createMock(MediaExtractorInterface::class);
        $extractor->expects($this->once())
            ->method('extract')
            ->with('https://example.com/v', AudioFormat::Wav, null)
            ->willReturn(new ExtractedAudio('/tmp/x.wav', 'x.wav', 'audio/wav'));

        $useCase = new ExtractAudio($extractor, $this->passthroughStorage());
        $useCase('https://example.com/v', 'wav', 320); // bitrate ignored for WAV
    }

    public function test_mp3_without_bitrate_uses_default(): void
    {
        $extractor = $this->createMock(MediaExtractorInterface::class);
        $extractor->expects($this->once())
            ->method('extract')
            ->with('https://example.com/v', AudioFormat::Mp3, ExtractAudio::DEFAULT_BITRATE_KBPS)
            ->willReturn(new ExtractedAudio('/tmp/x.mp3', 'x.mp3', 'audio/mpeg'));

        $useCase = new ExtractAudio($extractor, $this->passthroughStorage());
        $useCase('https://example.com/v', 'mp3', null);
    }

    public function test_empty_url_is_rejected(): void
    {
        $this->expectException(InvalidMediaRequestException::class);
        (new ExtractAudio($this->strictExtractor(), $this->strictStorage()))('   ', 'mp3', 192);
    }

    public function test_non_http_scheme_is_rejected(): void
    {
        $this->expectException(InvalidMediaRequestException::class);
        (new ExtractAudio($this->strictExtractor(), $this->strictStorage()))('file:///etc/passwd', 'mp3', 192);
    }

    public function test_ftp_scheme_is_rejected(): void
    {
        $this->expectException(InvalidMediaRequestException::class);
        (new ExtractAudio($this->strictExtractor(), $this->strictStorage()))('ftp://example.com/a', 'mp3', 192);
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->expectException(InvalidMediaRequestException::class);
        (new ExtractAudio($this->strictExtractor(), $this->strictStorage()))('https://example.com/v', 'flac', null);
    }

    public function test_invalid_bitrate_is_rejected(): void
    {
        $this->expectException(InvalidMediaRequestException::class);
        (new ExtractAudio($this->strictExtractor(), $this->strictStorage()))('https://example.com/v', 'mp3', 999);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Application\MessageHandler;

use App\Module\AudioExtractor\Application\ExtractAudio;
use App\Module\AudioExtractor\Application\MediaRequestValidator;
use App\Module\AudioExtractor\Application\Message\ExtractAudioMessage;
use App\Module\AudioExtractor\Application\MessageHandler\ExtractAudioMessageHandler;
use App\Module\AudioExtractor\Application\Port\AudioStorageInterface;
use App\Module\AudioExtractor\Application\Port\MediaExtractorInterface;
use App\Module\AudioExtractor\Domain\AudioJob;
use App\Module\AudioExtractor\Domain\ExtractedAudio;
use App\Module\AudioExtractor\Domain\StoredAudioFile;
use App\Tests\Module\AudioExtractor\Support\InMemoryAudioJobRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

class ExtractAudioMessageHandlerTest extends TestCase
{
    private function extractAudio(MediaExtractorInterface $extractor, AudioStorageInterface $storage): ExtractAudio
    {
        return new ExtractAudio(
            new MediaRequestValidator(),
            $extractor,
            $storage,
            new LockFactory(new InMemoryStore()),
            PHP_INT_MAX,
        );
    }

    public function test_pending_job_is_extracted_and_marked_done(): void
    {
        $jobs = new InMemoryAudioJobRepository();
        $job = new AudioJob('https://example.com/v', 'mp3', 256);
        $jobs->save($job);

        $extractor = $this->createMock(MediaExtractorInterface::class);
        $extractor->method('extract')->willReturn(new ExtractedAudio('/tmp/song.mp3', 'song.mp3', 'audio/mpeg'));
        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->method('totalSizeBytes')->willReturn(0);
        $storage->method('store')->willReturn(new StoredAudioFile('song.mp3', 10, new \DateTimeImmutable(), 'audio/mpeg'));

        $handler = new ExtractAudioMessageHandler($jobs, $this->extractAudio($extractor, $storage));
        $handler(new ExtractAudioMessage($job->getId()));

        $this->assertSame(AudioJob::STATUS_DONE, $job->getStatus());
        $this->assertSame('song.mp3', $job->getResultFile());
    }

    public function test_extraction_error_marks_job_failed_without_rethrow(): void
    {
        $jobs = new InMemoryAudioJobRepository();
        $job = new AudioJob('https://example.com/v', 'mp3', 256);
        $jobs->save($job);

        $extractor = $this->createMock(MediaExtractorInterface::class);
        $extractor->method('extract')->willThrowException(new \RuntimeException('yt-dlp exited 1'));
        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->method('totalSizeBytes')->willReturn(0);

        $handler = new ExtractAudioMessageHandler($jobs, $this->extractAudio($extractor, $storage));
        // Must not re-throw (D-033: failure is recorded, message acked).
        $handler(new ExtractAudioMessage($job->getId()));

        $this->assertSame(AudioJob::STATUS_FAILED, $job->getStatus());
        $this->assertSame('yt-dlp exited 1', $job->getError());
    }

    public function test_canceled_job_is_skipped(): void
    {
        $jobs = new InMemoryAudioJobRepository();
        $job = new AudioJob('https://example.com/v', 'mp3', 256);
        $job->cancel();
        $jobs->save($job);

        $extractor = $this->createMock(MediaExtractorInterface::class);
        $extractor->expects($this->never())->method('extract');
        $storage = $this->createMock(AudioStorageInterface::class);

        $handler = new ExtractAudioMessageHandler($jobs, $this->extractAudio($extractor, $storage));
        $handler(new ExtractAudioMessage($job->getId()));

        $this->assertSame(AudioJob::STATUS_CANCELED, $job->getStatus());
    }

    public function test_unknown_job_is_dropped_silently(): void
    {
        $jobs = new InMemoryAudioJobRepository();
        $extractor = $this->createMock(MediaExtractorInterface::class);
        $extractor->expects($this->never())->method('extract');

        $handler = new ExtractAudioMessageHandler($jobs, $this->extractAudio($extractor, $this->createMock(AudioStorageInterface::class)));
        $handler(new ExtractAudioMessage('00000000-0000-0000-0000-000000000000'));

        $this->assertSame([], $jobs->recent());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Infrastructure\Http;

use App\Module\AudioExtractor\Application\ExtractAudio;
use App\Module\AudioExtractor\Application\Port\AudioStorageInterface;
use App\Module\AudioExtractor\Application\Port\MediaEngineInterface;
use App\Module\AudioExtractor\Application\Port\MediaExtractorInterface;
use App\Module\AudioExtractor\Application\UpdateEngine;
use App\Module\AudioExtractor\Domain\ExtractedAudio;
use App\Module\AudioExtractor\Domain\StoredAudioFile;
use App\Module\AudioExtractor\Infrastructure\Http\AudioExtractorController;
use App\Shared\Application\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

class AudioExtractorControllerTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tmpFiles = [];
    }

    private function controller(
        MediaExtractorInterface $extractor,
        AudioStorageInterface $storage,
        MediaEngineInterface $engine,
    ): AudioExtractorController {
        $locks = new LockFactory(new InMemoryStore());

        return new AudioExtractorController(
            new ExtractAudio($extractor, $storage, $locks, PHP_INT_MAX),
            $storage,
            $engine,
            new UpdateEngine($engine, $locks),
            1800,
            240,
        );
    }

    private function engine(?string $version = '2026.06.01'): MediaEngineInterface
    {
        $mock = $this->createMock(MediaEngineInterface::class);
        $mock->method('version')->willReturn($version);

        return $mock;
    }

    private function storedFile(string $name = 'song.mp3'): StoredAudioFile
    {
        return new StoredAudioFile($name, 123, new \DateTimeImmutable('2026-06-03T12:00:00+00:00'), 'audio/mpeg');
    }

    public function test_config_reports_formats_and_engine_version(): void
    {
        $controller = $this->controller(
            $this->createMock(MediaExtractorInterface::class),
            $this->createMock(AudioStorageInterface::class),
            $this->engine('2026.06.01'),
        );

        $data = json_decode((string) $controller->config()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertContains(256, $data['bitrates_kbps']);
        $this->assertSame('2026.06.01', $data['engine_version']);
    }

    public function test_extract_returns_201_with_stored_file(): void
    {
        $extractor = $this->createMock(MediaExtractorInterface::class);
        $extractor->method('extract')->willReturn(new ExtractedAudio('/tmp/job.mp3', 'song.mp3', 'audio/mpeg'));

        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->expects($this->once())->method('store')->willReturn($this->storedFile('song.mp3'));

        $controller = $this->controller($extractor, $storage, $this->engine());

        $request = Request::create(
            '/api/v1/audio-extractor/extract',
            'POST',
            [], [], [], [],
            (string) json_encode(['url' => 'https://example.com/v', 'format' => 'mp3', 'bitrate_kbps' => 256]),
        );
        $response = $controller->extract($request);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('song.mp3', $data['name']);
        $this->assertStringContainsString('/audio-extractor/files/song.mp3', $data['download_url']);
    }

    public function test_list_files_returns_items_and_total(): void
    {
        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->method('list')->willReturn([$this->storedFile('a.mp3'), $this->storedFile('b.wav')]);
        $storage->method('totalSizeBytes')->willReturn(246);

        $controller = $this->controller(
            $this->createMock(MediaExtractorInterface::class),
            $storage,
            $this->engine(),
        );

        $data = json_decode((string) $controller->listFiles()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $data['items']);
        $this->assertSame(246, $data['total_size_bytes']);
    }

    public function test_download_known_file_streams_attachment(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sfx_dl_');
        $this->assertIsString($tmp);
        file_put_contents($tmp, 'audio');
        $this->tmpFiles[] = $tmp;

        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->method('absolutePath')->with('song.mp3')->willReturn($tmp);

        $controller = $this->controller(
            $this->createMock(MediaExtractorInterface::class),
            $storage,
            $this->engine(),
        );

        $response = $controller->downloadFile('song.mp3');
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_download_unknown_file_is_404(): void
    {
        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->method('absolutePath')->willReturn(null);

        $controller = $this->controller(
            $this->createMock(MediaExtractorInterface::class),
            $storage,
            $this->engine(),
        );

        $this->expectException(NotFoundException::class);
        $controller->downloadFile('ghost.mp3');
    }

    public function test_delete_known_file_returns_204(): void
    {
        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->method('delete')->with('song.mp3')->willReturn(true);

        $controller = $this->controller(
            $this->createMock(MediaExtractorInterface::class),
            $storage,
            $this->engine(),
        );

        $this->assertSame(204, $controller->deleteFile('song.mp3')->getStatusCode());
    }

    public function test_delete_unknown_file_is_404(): void
    {
        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->method('delete')->willReturn(false);

        $controller = $this->controller(
            $this->createMock(MediaExtractorInterface::class),
            $storage,
            $this->engine(),
        );

        $this->expectException(NotFoundException::class);
        $controller->deleteFile('ghost.mp3');
    }

    public function test_update_returns_new_version(): void
    {
        $engine = $this->createMock(MediaEngineInterface::class);
        $engine->expects($this->once())->method('update')->willReturn('2026.06.03');

        $controller = $this->controller(
            $this->createMock(MediaExtractorInterface::class),
            $this->createMock(AudioStorageInterface::class),
            $engine,
        );

        $data = json_decode((string) $controller->update()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('2026.06.03', $data['engine_version']);
    }
}

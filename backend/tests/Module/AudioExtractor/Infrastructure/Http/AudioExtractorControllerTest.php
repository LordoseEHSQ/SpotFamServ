<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Infrastructure\Http;

use App\Module\AudioExtractor\Application\CancelAudioJob;
use App\Module\AudioExtractor\Application\CreateAudioJob;
use App\Module\AudioExtractor\Application\MediaRequestValidator;
use App\Module\AudioExtractor\Application\Port\AudioStorageInterface;
use App\Module\AudioExtractor\Application\Port\MediaEngineInterface;
use App\Module\AudioExtractor\Application\UpdateEngine;
use App\Module\AudioExtractor\Domain\StoredAudioFile;
use App\Module\AudioExtractor\Infrastructure\Http\AudioExtractorController;
use App\Shared\Application\Exception\NotFoundException;
use App\Tests\Module\AudioExtractor\Support\InMemoryAudioJobRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class AudioExtractorControllerTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    private InMemoryAudioJobRepository $jobs;

    protected function setUp(): void
    {
        $this->jobs = new InMemoryAudioJobRepository();
    }

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
        AudioStorageInterface $storage,
        MediaEngineInterface $engine,
    ): AudioExtractorController {
        $locks = new LockFactory(new InMemoryStore());

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(
            static fn (object $message): Envelope => new Envelope($message),
        );

        $validator = new MediaRequestValidator();

        return new AudioExtractorController(
            new CreateAudioJob($validator, $this->jobs, $bus),
            new CancelAudioJob($this->jobs),
            $this->jobs,
            $storage,
            $engine,
            new UpdateEngine($engine, $locks),
            1800,
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
            $this->createMock(AudioStorageInterface::class),
            $this->engine('2026.06.01'),
        );

        $data = json_decode((string) $controller->config()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertContains(256, $data['bitrates_kbps']);
        $this->assertSame('2026.06.01', $data['engine_version']);
    }

    public function test_extract_enqueues_job_and_returns_202(): void
    {
        // Storage is irrelevant here: extraction is async, so the controller must never touch it.
        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->expects($this->never())->method('store');

        $controller = $this->controller($storage, $this->engine());

        $request = Request::create(
            '/api/v1/audio-extractor/extract',
            'POST',
            [], [], [], [],
            (string) json_encode(['url' => 'https://example.com/v', 'format' => 'mp3', 'bitrate_kbps' => 256]),
        );
        $response = $controller->extract($request);

        $this->assertSame(202, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($data['id']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame('mp3', $data['format']);
        $this->assertSame(256, $data['bitrate_kbps']);
        $this->assertNull($data['result_file']);
        // The job is now retrievable via the status API.
        $this->assertNotNull($this->jobs->findById($data['id']));
    }

    public function test_extract_with_invalid_url_is_422_and_creates_no_job(): void
    {
        $controller = $this->controller($this->createMock(AudioStorageInterface::class), $this->engine());

        $request = Request::create(
            '/api/v1/audio-extractor/extract',
            'POST',
            [], [], [], [],
            (string) json_encode(['url' => 'ftp://example.com/v', 'format' => 'mp3']),
        );

        try {
            $controller->extract($request);
            $this->fail('Expected an InvalidMediaRequestException.');
        } catch (\App\Module\AudioExtractor\Domain\InvalidMediaRequestException) {
            $this->assertSame([], $this->jobs->recent());
        }
    }

    public function test_get_unknown_job_is_404(): void
    {
        $controller = $this->controller($this->createMock(AudioStorageInterface::class), $this->engine());

        $this->expectException(NotFoundException::class);
        $controller->getJob('00000000-0000-0000-0000-000000000000');
    }

    public function test_list_files_returns_items_and_total(): void
    {
        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->method('list')->willReturn([$this->storedFile('a.mp3'), $this->storedFile('b.wav')]);
        $storage->method('totalSizeBytes')->willReturn(246);

        $controller = $this->controller($storage, $this->engine());

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

        $controller = $this->controller($storage, $this->engine());

        $response = $controller->downloadFile('song.mp3');
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_download_unknown_file_is_404(): void
    {
        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->method('absolutePath')->willReturn(null);

        $controller = $this->controller($storage, $this->engine());

        $this->expectException(NotFoundException::class);
        $controller->downloadFile('ghost.mp3');
    }

    public function test_delete_known_file_returns_204(): void
    {
        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->method('delete')->with('song.mp3')->willReturn(true);

        $controller = $this->controller($storage, $this->engine());

        $this->assertSame(204, $controller->deleteFile('song.mp3')->getStatusCode());
    }

    public function test_delete_unknown_file_is_404(): void
    {
        $storage = $this->createMock(AudioStorageInterface::class);
        $storage->method('delete')->willReturn(false);

        $controller = $this->controller($storage, $this->engine());

        $this->expectException(NotFoundException::class);
        $controller->deleteFile('ghost.mp3');
    }

    public function test_update_returns_new_version(): void
    {
        $engine = $this->createMock(MediaEngineInterface::class);
        $engine->expects($this->once())->method('update')->willReturn('2026.06.03');

        $controller = $this->controller($this->createMock(AudioStorageInterface::class), $engine);

        $data = json_decode((string) $controller->update()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('2026.06.03', $data['engine_version']);
    }
}

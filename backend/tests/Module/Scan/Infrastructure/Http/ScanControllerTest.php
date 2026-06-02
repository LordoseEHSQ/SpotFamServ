<?php

declare(strict_types=1);

namespace App\Tests\Module\Scan\Infrastructure\Http;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\Scan\Application\ListScanEvents;
use App\Module\Scan\Application\Port\PlaybackSessionStoreInterface;
use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Application\Port\ScanCardResolverInterface;
use App\Module\Scan\Application\Port\ScanEventRepositoryInterface;
use App\Module\Scan\Application\ProcessReaderControl;
use App\Module\Scan\Application\ProcessScan;
use App\Module\Scan\Domain\ReaderDevice;
use App\Module\Scan\Infrastructure\Http\ScanController;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;
use App\Module\Spotify\Application\SkipToNext;
use App\Module\Spotify\Application\SkipToPrevious;
use App\Module\Spotify\Application\StartPlayback;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Auth-decision tests for the per-reader API key (D-K1, Option B).
 *
 * We assert via status code: 401 = rejected, 400 = passed auth then failed the
 * missing-card_uid check (proves the request got past the auth gate without
 * needing to exercise the full playback pipeline).
 */
class ScanControllerTest extends TestCase
{
    private const PASSED_AUTH = 400;
    private const REJECTED = 401;

    private function controller(string $globalKey, ReaderDeviceRepositoryInterface $readers): ScanController
    {
        $processScan = new ProcessScan(
            $this->createMock(ScanEventRepositoryInterface::class),
            $this->createMock(ScanCardResolverInterface::class),
            $this->createMock(ReaderDeviceRepositoryInterface::class),
            new StartPlayback(
                $this->createMock(SpotifyTokenManagerInterface::class),
                $this->createMock(SpotifyApiClientInterface::class),
                $this->createMock(FamilyProfileRepositoryInterface::class),
            ),
            $this->createMock(PlaybackSessionStoreInterface::class),
        );
        $listScanEvents = new ListScanEvents($this->createMock(ScanEventRepositoryInterface::class));
        $control = new ProcessReaderControl(
            $this->createMock(PlaybackSessionStoreInterface::class),
            new SkipToNext($this->createMock(SpotifyTokenManagerInterface::class), $this->createMock(SpotifyApiClientInterface::class)),
            new SkipToPrevious($this->createMock(SpotifyTokenManagerInterface::class), $this->createMock(SpotifyApiClientInterface::class)),
        );

        return new ScanController($processScan, $listScanEvents, $control, $readers, $globalKey);
    }

    private function readerRepo(?ReaderDevice $reader): ReaderDeviceRepositoryInterface
    {
        $repo = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $repo->method('findByReaderId')->willReturn($reader);
        return $repo;
    }

    /**
     * @param array<string, string> $headers e.g. ['X-API-Key' => '...']
     */
    private function scanRequest(string $readerId, array $headers = []): Request
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . str_replace('-', '_', strtoupper($name))] = $value;
        }
        // Intentionally omit card_uid: a passed auth then yields 400 (missing card_uid).
        return Request::create('/api/v1/readers/scan', 'POST', [], [], [], $server, json_encode(['reader_id' => $readerId]) ?: '');
    }

    public function test_per_reader_key_valid_is_allowed(): void
    {
        $reader = new ReaderDevice('reader-1');
        $reader->setApiKey('reader-secret');
        $controller = $this->controller('global-key', $this->readerRepo($reader));

        $response = $controller->scan($this->scanRequest('reader-1', ['X-API-Key' => 'reader-secret']));

        $this->assertSame(self::PASSED_AUTH, $response->getStatusCode());
    }

    public function test_per_reader_key_wrong_is_rejected(): void
    {
        $reader = new ReaderDevice('reader-1');
        $reader->setApiKey('reader-secret');
        $controller = $this->controller('global-key', $this->readerRepo($reader));

        $response = $controller->scan($this->scanRequest('reader-1', ['X-API-Key' => 'wrong']));

        $this->assertSame(self::REJECTED, $response->getStatusCode());
    }

    public function test_global_key_is_not_accepted_for_reader_with_own_key(): void
    {
        $reader = new ReaderDevice('reader-1');
        $reader->setApiKey('reader-secret');
        $controller = $this->controller('global-key', $this->readerRepo($reader));

        $response = $controller->scan($this->scanRequest('reader-1', ['X-API-Key' => 'global-key']));

        $this->assertSame(self::REJECTED, $response->getStatusCode());
    }

    public function test_reader_without_key_falls_back_to_global_key(): void
    {
        $reader = new ReaderDevice('reader-1');
        $controller = $this->controller('global-key', $this->readerRepo($reader));

        $response = $controller->scan($this->scanRequest('reader-1', ['X-API-Key' => 'global-key']));

        $this->assertSame(self::PASSED_AUTH, $response->getStatusCode());
    }

    public function test_reader_without_key_rejects_wrong_global_key(): void
    {
        $reader = new ReaderDevice('reader-1');
        $controller = $this->controller('global-key', $this->readerRepo($reader));

        $response = $controller->scan($this->scanRequest('reader-1', ['X-API-Key' => 'nope']));

        $this->assertSame(self::REJECTED, $response->getStatusCode());
    }

    public function test_unknown_reader_falls_back_to_global_key(): void
    {
        $controller = $this->controller('global-key', $this->readerRepo(null));

        $response = $controller->scan($this->scanRequest('ghost', ['Authorization' => 'Bearer global-key']));

        $this->assertSame(self::PASSED_AUTH, $response->getStatusCode());
    }

    public function test_open_when_no_global_and_no_reader_key(): void
    {
        $reader = new ReaderDevice('reader-1');
        $controller = $this->controller('', $this->readerRepo($reader));

        $response = $controller->scan($this->scanRequest('reader-1'));

        $this->assertSame(self::PASSED_AUTH, $response->getStatusCode());
    }

    public function test_reader_with_key_still_enforced_even_if_global_empty(): void
    {
        $reader = new ReaderDevice('reader-1');
        $reader->setApiKey('reader-secret');
        $controller = $this->controller('', $this->readerRepo($reader));

        $response = $controller->scan($this->scanRequest('reader-1'));

        $this->assertSame(self::REJECTED, $response->getStatusCode());
    }
}

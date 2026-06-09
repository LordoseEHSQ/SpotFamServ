<?php

declare(strict_types=1);

namespace App\Tests\Module\Scan\Infrastructure\Http;

use App\Module\Provisioning\Application\Port\FlashArtifactRepositoryInterface;
use App\Module\Provisioning\Domain\FlashArtifact;
use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Infrastructure\Http\ReaderFirmwareController;
use App\Module\System\Application\Port\SystemConfigurationProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ReaderFirmwareControllerTest extends TestCase
{
    private function makeController(string $otaChannel = 'stable', ?FlashArtifactRepositoryInterface $artifacts = null, ?string $firmwareDir = null): ReaderFirmwareController
    {
        $systemConfig = $this->createMock(SystemConfigurationProviderInterface::class);
        $systemConfig->method('getOtaChannel')->willReturn($otaChannel);

        if ($artifacts === null) {
            $artifacts = $this->createMock(FlashArtifactRepositoryInterface::class);
            $artifacts->method('findByBoardChannel')->willReturn([]);
        }

        $readerDevices = $this->createMock(ReaderDeviceRepositoryInterface::class);
        $readerDevices->method('findByReaderId')->willReturn(null);

        return new ReaderFirmwareController($systemConfig, $artifacts, $readerDevices, $firmwareDir ?? sys_get_temp_dir());
    }

    public function test_supported_board_without_artifact_returns_no_content(): void
    {
        $controller = $this->makeController();
        $response = $controller->manifest(Request::create('/api/v1/readers/firmware/manifest?board=esp32-wroom-32&channel=stable&current_version=0.1.0'));

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function test_unsupported_board_is_rejected(): void
    {
        $controller = $this->makeController();
        $response = $controller->manifest(Request::create('/api/v1/readers/firmware/manifest?board=esp8266&channel=stable&current_version=0.1.0'));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('unsupported_board', $payload['error']);
    }

    public function test_current_version_must_be_semver(): void
    {
        $controller = $this->makeController();
        $response = $controller->manifest(Request::create('/api/v1/readers/firmware/manifest?board=esp32-wroom-32&channel=stable&current_version=dev'));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame('invalid_request', $payload['error']);
    }

    public function test_configured_ota_channel_is_used(): void
    {
        $controller = $this->makeController('beta');
        // Default-Kanal kommt nun aus der System-Config: 'beta' wird akzeptiert ...
        $ok = $controller->manifest(Request::create('/api/v1/readers/firmware/manifest?board=esp32-wroom-32&channel=beta&current_version=0.1.0'));
        $this->assertSame(Response::HTTP_NO_CONTENT, $ok->getStatusCode());

        // ... 'stable' wird abgelehnt, weil der aktive Kanal 'beta' ist.
        $rejected = $controller->manifest(Request::create('/api/v1/readers/firmware/manifest?board=esp32-wroom-32&channel=stable&current_version=0.1.0'));
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $rejected->getStatusCode());
    }

    public function test_manifest_returns_latest_newer_artifact(): void
    {
        $old = new FlashArtifact('esp32-wroom-32', 'stable', '0.1.0', 'old.bin', str_repeat('a', 64), 'ESP32-D0WD-V3', 10);
        $new = new FlashArtifact('esp32-wroom-32', 'stable', '0.2.0', 'new.bin', str_repeat('b', 64), 'ESP32-D0WD-V3', 20);
        $olderThanCurrent = new FlashArtifact('esp32-wroom-32', 'stable', '0.0.9', 'older.bin', str_repeat('c', 64), 'ESP32-D0WD-V3', 5);

        $artifacts = $this->createMock(FlashArtifactRepositoryInterface::class);
        $artifacts->method('findByBoardChannel')->with('esp32-wroom-32', 'stable')->willReturn([$old, $new, $olderThanCurrent]);
        $controller = $this->makeController(artifacts: $artifacts);

        $response = $controller->manifest(Request::create('/api/v1/readers/firmware/manifest?board=esp32-wroom-32&channel=stable&current_version=0.1.0'));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('0.2.0', $payload['version']);
        $this->assertSame(str_repeat('b', 64), $payload['sha256']);
        $this->assertSame('/api/v1/readers/firmware/esp32-wroom-32/stable/0.2.0.bin', $payload['download_url']);
    }

    public function test_download_serves_registered_file_with_hash_header(): void
    {
        $dir = sys_get_temp_dir() . '/spotfam-fw-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/reader.bin', 'firmware');
        $artifact = new FlashArtifact('esp32-wroom-32', 'stable', '0.2.0', 'reader.bin', str_repeat('d', 64), 'ESP32-D0WD-V3', 8);

        $artifacts = $this->createMock(FlashArtifactRepositoryInterface::class);
        $artifacts->method('findByBoardChannel')->willReturn([]);
        $artifacts->method('findByBoardChannelVersion')->with('esp32-wroom-32', 'stable', '0.2.0')->willReturn($artifact);
        $controller = $this->makeController(artifacts: $artifacts, firmwareDir: $dir);

        $response = $controller->download('esp32-wroom-32', 'stable', '0.2.0');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(str_repeat('d', 64), $response->headers->get('X-Firmware-Sha256'));

        unlink($dir . '/reader.bin');
        rmdir($dir);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Module\Scan\Infrastructure\Http;

use App\Module\Scan\Infrastructure\Http\ReaderFirmwareController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ReaderFirmwareControllerTest extends TestCase
{
    public function test_supported_board_without_artifact_returns_no_content(): void
    {
        $controller = new ReaderFirmwareController();
        $response = $controller->manifest(Request::create('/api/v1/readers/firmware/manifest?board=esp32-wroom-32&channel=stable&current_version=0.1.0'));

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function test_unsupported_board_is_rejected(): void
    {
        $controller = new ReaderFirmwareController();
        $response = $controller->manifest(Request::create('/api/v1/readers/firmware/manifest?board=esp8266&channel=stable&current_version=0.1.0'));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('unsupported_board', $payload['error']);
    }

    public function test_current_version_must_be_semver(): void
    {
        $controller = new ReaderFirmwareController();
        $response = $controller->manifest(Request::create('/api/v1/readers/firmware/manifest?board=esp32-wroom-32&channel=stable&current_version=dev'));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame('invalid_request', $payload['error']);
    }
}

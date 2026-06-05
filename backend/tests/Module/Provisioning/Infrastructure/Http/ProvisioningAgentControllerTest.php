<?php

declare(strict_types=1);

namespace App\Tests\Module\Provisioning\Infrastructure\Http;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\Provisioning\Application\DetectDevice;
use App\Module\Provisioning\Application\DetectDeviceResult;
use App\Module\Provisioning\Application\GetNextJob;
use App\Module\Provisioning\Application\Port\DetectedDeviceRepositoryInterface;
use App\Module\Provisioning\Application\Port\FlashJobRepositoryInterface;
use App\Module\Provisioning\Application\UpdateJobStatus;
use App\Module\Provisioning\Application\Port\FlashArtifactRepositoryInterface;
use App\Module\Provisioning\Application\Port\FlashJobRepositoryInterface as FlashJobRepo;
use App\Module\Provisioning\Infrastructure\Http\ProvisioningAgentController;
use App\Module\System\Application\Port\SystemConfigurationProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ProvisioningAgentControllerTest extends TestCase
{
    private function makeController(
        string $apiKey = '',
        ?SystemConfigurationProviderInterface $systemConfig = null,
        string $readerApiKey = '',
    ): ProvisioningAgentController {
        $deviceRepo  = $this->createMock(DetectedDeviceRepositoryInterface::class);
        $activityLog = $this->createMock(ActivityLogRepositoryInterface::class);
        $jobRepo     = $this->createMock(FlashJobRepositoryInterface::class);

        // Gerät wird als neu zurückgegeben
        $deviceRepo->method('findByMac')->willReturn(null);
        $deviceRepo->method('findById')->willReturn(null);

        $detectDevice    = new DetectDevice($deviceRepo, $activityLog);
        $getNextJob      = new GetNextJob($deviceRepo, $jobRepo);

        // UpdateJobStatus: braucht jobs + devices + activityLog
        $jobRepo2     = $this->createMock(FlashJobRepositoryInterface::class);
        $deviceRepo2  = $this->createMock(DetectedDeviceRepositoryInterface::class);
        $updateJobStatus = new UpdateJobStatus($jobRepo2, $deviceRepo2, $activityLog);

        $systemConfig ??= $this->createMock(SystemConfigurationProviderInterface::class);

        return new ProvisioningAgentController(
            $detectDevice,
            $getNextJob,
            $updateJobStatus,
            $systemConfig,
            $apiKey,
            $readerApiKey,
        );
    }

    private function jsonRequest(string $method, string $uri, array $body = [], array $headers = []): Request
    {
        $request = Request::create($uri, $method, [], [], [], [], json_encode($body) ?: '');
        $request->headers->set('Content-Type', 'application/json');
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }
        return $request;
    }

    public function test_detect_device_returns_200_without_key_in_dev_mode(): void
    {
        $controller = $this->makeController('');
        $request    = $this->jsonRequest('POST', '/api/v1/provisioning/devices/detect', [
            'port'            => 'COM3',
            'chip'            => 'ESP32',
            'chipDescription' => 'ESP32-D0WD-V3',
            'mac'             => 'aa:bb:cc:dd:ee:ff',
            'flashSize'       => '4MB',
        ]);

        $response = $controller->detectDevice($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('deviceId', $data);
        $this->assertSame('idle', $data['status']);
    }

    public function test_detect_device_returns_401_with_wrong_key(): void
    {
        $controller = $this->makeController('correct-key');
        $request    = $this->jsonRequest('POST', '/api/v1/provisioning/devices/detect', [
            'port'      => 'COM3',
            'chip'      => 'ESP32',
            'mac'       => 'aa:bb:cc:dd:ee:ff',
            'flashSize' => '4MB',
        ], ['X-API-Key' => 'wrong-key']);

        $response = $controller->detectDevice($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_detect_device_accepts_correct_key(): void
    {
        $controller = $this->makeController('my-secret');
        $request    = $this->jsonRequest('POST', '/api/v1/provisioning/devices/detect', [
            'port'            => 'COM3',
            'chip'            => 'ESP32',
            'chipDescription' => 'desc',
            'mac'             => 'aa:bb:cc:dd:ee:ff',
            'flashSize'       => '4MB',
        ], ['X-API-Key' => 'my-secret']);

        $response = $controller->detectDevice($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_detect_device_returns_400_when_mac_missing(): void
    {
        $controller = $this->makeController('');
        $request    = $this->jsonRequest('POST', '/api/v1/provisioning/devices/detect', [
            'port' => 'COM3',
            'chip' => 'ESP32',
        ]);

        $response = $controller->detectDevice($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_get_next_job_returns_400_when_device_id_missing(): void
    {
        $controller = $this->makeController('');
        $request    = Request::create('/api/v1/provisioning/jobs/next', 'GET');

        $response = $controller->getNextJob($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_reader_config_returns_full_payload(): void
    {
        $provider = $this->createMock(SystemConfigurationProviderInterface::class);
        $provider->method('getWifiSsid')->willReturn('Heimnetz');
        $provider->method('getWifiPassword')->willReturn('s3cr3t');
        $provider->method('getBackendBaseUrl')->willReturn('http://192.168.1.91:8080');
        $provider->method('getOtaChannel')->willReturn('stable');

        $controller = $this->makeController('', $provider, 'reader-key-123');
        $response   = $controller->readerConfig(Request::create('/api/v1/provisioning/reader-config', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('Heimnetz', $data['wifiSsid']);
        $this->assertSame('s3cr3t', $data['wifiPassword']);
        $this->assertSame('http://192.168.1.91:8080', $data['backendBaseUrl']);
        $this->assertSame('stable', $data['otaChannel']);
        $this->assertSame('reader-key-123', $data['readerApiKey']);
        $this->assertTrue($data['complete']);
    }

    public function test_reader_config_incomplete_when_wifi_missing(): void
    {
        $provider = $this->createMock(SystemConfigurationProviderInterface::class);
        $provider->method('getWifiSsid')->willReturn(null);
        $provider->method('getWifiPassword')->willReturn(null);
        $provider->method('getBackendBaseUrl')->willReturn('http://192.168.1.91:8080');
        $provider->method('getOtaChannel')->willReturn('stable');

        $controller = $this->makeController('', $provider, '');
        $response   = $controller->readerConfig(Request::create('/api/v1/provisioning/reader-config', 'GET'));

        $data = json_decode((string) $response->getContent(), true);
        $this->assertFalse($data['complete']);
        $this->assertNull($data['wifiSsid']);
        $this->assertNull($data['readerApiKey']);
    }

    public function test_reader_config_returns_401_with_wrong_key(): void
    {
        $controller = $this->makeController('correct-key');
        $request    = Request::create('/api/v1/provisioning/reader-config', 'GET');
        $request->headers->set('X-API-Key', 'wrong-key');

        $response = $controller->readerConfig($request);

        $this->assertSame(401, $response->getStatusCode());
    }
}

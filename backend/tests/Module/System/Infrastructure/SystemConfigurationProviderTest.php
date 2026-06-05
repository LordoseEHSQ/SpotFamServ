<?php

declare(strict_types=1);

namespace App\Tests\Module\System\Infrastructure;

use App\Module\System\Application\Port\SystemConfigurationRepositoryInterface;
use App\Module\System\Domain\SystemConfiguration;
use App\Module\System\Infrastructure\SystemConfigurationProvider;
use PHPUnit\Framework\TestCase;

class SystemConfigurationProviderTest extends TestCase
{
    private function provider(?SystemConfiguration $config, string $envFrontendUrl = 'http://env-frontend'): SystemConfigurationProvider
    {
        $repo = $this->createMock(SystemConfigurationRepositoryInterface::class);
        $repo->method('findActive')->willReturn($config);

        return new SystemConfigurationProvider($repo, $envFrontendUrl);
    }

    public function test_frontend_url_prefers_db_value(): void
    {
        $config = new SystemConfiguration();
        $config->setFrontendUrl('http://db-frontend');

        self::assertSame('http://db-frontend', $this->provider($config)->getFrontendUrl());
    }

    public function test_frontend_url_falls_back_to_env_when_db_empty(): void
    {
        self::assertSame('http://env-frontend', $this->provider(null)->getFrontendUrl());

        // Aktive Zeile ohne frontend_url → ebenfalls Env-Fallback (pro-Feld, D-029).
        self::assertSame('http://env-frontend', $this->provider(new SystemConfiguration())->getFrontendUrl());
    }

    public function test_ota_channel_prefers_db_else_default_stable(): void
    {
        self::assertSame('stable', $this->provider(null)->getOtaChannel());

        $config = new SystemConfiguration();
        $config->setOtaChannel('beta');
        self::assertSame('beta', $this->provider($config)->getOtaChannel());
    }

    public function test_invalid_ota_channel_is_coerced_to_default(): void
    {
        $config = new SystemConfiguration();
        $config->setOtaChannel('garbage');
        self::assertSame('stable', $this->provider($config)->getOtaChannel());
    }

    public function test_backend_url_and_wifi_are_db_only(): void
    {
        self::assertNull($this->provider(null)->getBackendBaseUrl());
        self::assertNull($this->provider(null)->getWifiSsid());
        self::assertNull($this->provider(null)->getWifiPassword());

        $config = new SystemConfiguration();
        $config->setBackendBaseUrl('http://192.168.1.91:8080');
        $config->setWifiSsid('Home');
        $config->setWifiPassword('secret');

        $p = $this->provider($config);
        self::assertSame('http://192.168.1.91:8080', $p->getBackendBaseUrl());
        self::assertSame('Home', $p->getWifiSsid());
        self::assertSame('secret', $p->getWifiPassword());
    }

    public function test_reader_network_completeness(): void
    {
        $config = new SystemConfiguration();
        self::assertFalse($config->isReaderNetworkComplete());

        $config->setWifiSsid('Home');
        $config->setWifiPassword('secret');
        $config->setBackendBaseUrl('http://192.168.1.91:8080');
        self::assertTrue($config->isReaderNetworkComplete());
    }
}

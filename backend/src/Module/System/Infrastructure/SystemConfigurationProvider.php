<?php

declare(strict_types=1);

namespace App\Module\System\Infrastructure;

use App\Module\System\Application\Port\SystemConfigurationProviderInterface;
use App\Module\System\Application\Port\SystemConfigurationRepositoryInterface;
use App\Module\System\Domain\SystemConfiguration;

final readonly class SystemConfigurationProvider implements SystemConfigurationProviderInterface
{
    public function __construct(
        private SystemConfigurationRepositoryInterface $repository,
        private string $envFrontendUrl,
    ) {
    }

    public function getFrontendUrl(): string
    {
        $url = $this->repository->findActive()?->getFrontendUrl();
        if ($url !== null && $url !== '') {
            return $url;
        }
        return $this->envFrontendUrl;
    }

    public function getOtaChannel(): string
    {
        $channel = $this->repository->findActive()?->getOtaChannel();
        if ($channel !== null && $channel !== '') {
            return $channel;
        }
        return SystemConfiguration::DEFAULT_OTA_CHANNEL;
    }

    public function getBackendBaseUrl(): ?string
    {
        $url = $this->repository->findActive()?->getBackendBaseUrl();
        return ($url !== null && $url !== '') ? $url : null;
    }

    public function getWifiSsid(): ?string
    {
        $ssid = $this->repository->findActive()?->getWifiSsid();
        return ($ssid !== null && $ssid !== '') ? $ssid : null;
    }

    public function getWifiPassword(): ?string
    {
        $pw = $this->repository->findActive()?->getWifiPassword();
        return ($pw !== null && $pw !== '') ? $pw : null;
    }
}

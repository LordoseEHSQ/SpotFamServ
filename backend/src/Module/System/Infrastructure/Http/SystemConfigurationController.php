<?php

declare(strict_types=1);

namespace App\Module\System\Infrastructure\Http;

use App\Module\System\Application\GetSystemConfiguration;
use App\Module\System\Application\SaveSystemConfiguration;
use App\Module\System\Domain\SystemConfiguration;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Systemweite Betriebs-Konfiguration: Reader-Netzwerk (WLAN/Backend/OTA) + Frontend-URL (D-029).
 * GET liefert NIE Secrets (nur `has_wifi_password`-Flag). ROLE_ADMIN via Catch-all (security.yaml).
 */
#[Route(path: '/system/configuration', name: 'api_system_config_', format: 'json')]
final class SystemConfigurationController
{
    public function __construct(
        private readonly GetSystemConfiguration $getConfig,
        private readonly SaveSystemConfiguration $saveConfig,
    ) {
    }

    #[Route(path: '', name: 'get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        ['config' => $config, 'source' => $source] = ($this->getConfig)();

        return new JsonResponse([
            'source' => $source,
            'wifi_ssid' => $config->getWifiSsid(),
            'has_wifi_password' => $config->getWifiPassword() !== null && $config->getWifiPassword() !== '',
            'backend_base_url' => $config->getBackendBaseUrl(),
            'ota_channel' => $config->getOtaChannel(),
            'ota_channels' => SystemConfiguration::OTA_CHANNELS,
            'frontend_url' => $config->getFrontendUrl(),
            'reader_network_complete' => $config->isReaderNetworkComplete(),
            'updated_at' => $config->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route(path: '', name: 'save', methods: ['PUT'])]
    public function save(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $body */
        $body = $request->toArray();

        if (isset($body['ota_channel'])
            && !in_array((string) $body['ota_channel'], SystemConfiguration::OTA_CHANNELS, true)
        ) {
            return new JsonResponse([
                'error' => 'invalid_ota_channel',
                'detail' => 'ota_channel muss eines von: ' . implode(', ', SystemConfiguration::OTA_CHANNELS),
            ], 400);
        }

        // Nur tatsächlich gesendete Felder weiterreichen (Teil-Update nullt keine anderen Felder).
        $str = static fn (mixed $v): ?string => $v !== null ? (string) $v : null;
        $data = [];
        if (array_key_exists('wifi_ssid', $body)) {
            $data['wifi_ssid'] = $str($body['wifi_ssid']);
        }
        if (array_key_exists('wifi_password', $body)) {
            $data['wifi_password'] = $str($body['wifi_password']);
        }
        if (array_key_exists('backend_base_url', $body)) {
            $data['backend_base_url'] = $str($body['backend_base_url']);
        }
        if (array_key_exists('ota_channel', $body)) {
            $data['ota_channel'] = $str($body['ota_channel']);
        }
        if (array_key_exists('frontend_url', $body)) {
            $data['frontend_url'] = $str($body['frontend_url']);
        }

        $config = ($this->saveConfig)($data);

        return new JsonResponse([
            'reader_network_complete' => $config->isReaderNetworkComplete(),
            'updated_at' => $config->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}

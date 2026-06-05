<?php

declare(strict_types=1);

namespace App\Module\System\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\System\Application\Port\SystemConfigurationRepositoryInterface;
use App\Module\System\Domain\SystemConfiguration;

final readonly class SaveSystemConfiguration
{
    public function __construct(
        private SystemConfigurationRepositoryInterface $repository,
        private ActivityLogRepositoryInterface $activityLog,
    ) {
    }

    /**
     * Aktualisiert nur die übergebenen Felder. `wifi_password` wird nur gesetzt, wenn ein
     * nicht-leerer Wert geliefert wird (Masking-Muster analog Spotify-Secret).
     *
     * @param array{
     *     wifi_ssid?: string|null,
     *     wifi_password?: string|null,
     *     backend_base_url?: string|null,
     *     ota_channel?: string|null,
     *     frontend_url?: string|null
     * } $data
     */
    public function __invoke(array $data): SystemConfiguration
    {
        $config = $this->repository->findActive() ?? new SystemConfiguration();

        if (array_key_exists('wifi_ssid', $data)) {
            $config->setWifiSsid($data['wifi_ssid'] !== null && $data['wifi_ssid'] !== '' ? $data['wifi_ssid'] : null);
        }
        if (array_key_exists('wifi_password', $data) && $data['wifi_password'] !== null && $data['wifi_password'] !== '') {
            $config->setWifiPassword($data['wifi_password']);
        }
        if (array_key_exists('backend_base_url', $data)) {
            $config->setBackendBaseUrl($data['backend_base_url'] !== null && $data['backend_base_url'] !== '' ? $data['backend_base_url'] : null);
        }
        if (array_key_exists('ota_channel', $data) && $data['ota_channel'] !== null && $data['ota_channel'] !== '') {
            $config->setOtaChannel($data['ota_channel']);
        }
        if (array_key_exists('frontend_url', $data)) {
            $config->setFrontendUrl($data['frontend_url'] !== null && $data['frontend_url'] !== '' ? $data['frontend_url'] : null);
        }

        $this->repository->save($config);

        $this->activityLog->append(new ActivityLog(
            ActivityLog::TYPE_SYSTEM,
            'Systemkonfiguration (Reader-Netzwerk) gespeichert',
            ActivityLog::SEVERITY_INFO,
            null,
            null,
            null,
            ['ota_channel' => $config->getOtaChannel()],
        ));

        return $config;
    }
}

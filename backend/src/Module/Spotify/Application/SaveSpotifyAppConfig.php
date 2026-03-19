<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Spotify\Application\Port\SpotifyAppConfigRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyAppConfiguration;

final readonly class SaveSpotifyAppConfig
{
    public function __construct(
        private SpotifyAppConfigRepositoryInterface $configRepository,
        private ActivityLogRepositoryInterface $activityLog,
    ) {
    }

    /**
     * @param array{client_id?: string|null, client_secret?: string|null, redirect_uri?: string|null, scope_defaults?: string|null} $data
     */
    public function __invoke(array $data): SpotifyAppConfiguration
    {
        $config = $this->configRepository->findActive() ?? new SpotifyAppConfiguration();

        if (array_key_exists('client_id', $data)) {
            $config->setSpotifyClientId($data['client_id'] ?: null);
        }
        if (array_key_exists('client_secret', $data) && $data['client_secret'] !== null && $data['client_secret'] !== '') {
            $config->setSpotifyClientSecret($data['client_secret']);
        }
        if (array_key_exists('redirect_uri', $data)) {
            $config->setRedirectUri($data['redirect_uri'] ?: null);
        }
        if (array_key_exists('scope_defaults', $data)) {
            $config->setScopeDefaults($data['scope_defaults'] ?: null);
        }

        $config->setConfigStatus(
            $config->isComplete()
                ? SpotifyAppConfiguration::STATUS_CONFIGURED
                : SpotifyAppConfiguration::STATUS_UNCONFIGURED
        );

        $this->configRepository->save($config);

        $entry = new ActivityLog(
            ActivityLog::TYPE_SYSTEM,
            'Globale Spotify-App-Konfiguration gespeichert',
            ActivityLog::SEVERITY_INFO,
            null,
            null,
            null,
            ['config_status' => $config->getConfigStatus()],
        );
        $this->activityLog->append($entry);

        return $config;
    }
}

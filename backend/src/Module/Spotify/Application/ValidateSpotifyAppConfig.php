<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Spotify\Application\Port\SpotifyAppConfigRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyAppConfiguration;
use App\Module\Spotify\Domain\Exception\SpotifyException;

final readonly class ValidateSpotifyAppConfig
{
    public function __construct(
        private SpotifyAppConfigRepositoryInterface $configRepository,
        private ActivityLogRepositoryInterface $activityLog,
        private string $envClientId,
        private string $envRedirectUri,
    ) {
    }

    /**
     * @return array{valid: bool, status: string, note: string}
     */
    public function __invoke(): array
    {
        $config = $this->configRepository->findActive();

        $clientId = $config?->getSpotifyClientId() ?? $this->envClientId;
        $redirectUri = $config?->getRedirectUri() ?? $this->envRedirectUri;

        $valid = $clientId !== '' && $clientId !== null
            && $redirectUri !== '' && $redirectUri !== null;

        $note = $valid
            ? 'Konfiguration vollständig. Client ID und Redirect URI vorhanden.'
            : 'Konfiguration unvollständig: Client ID oder Redirect URI fehlt.';

        if ($config !== null) {
            $config->recordCheck($valid, $note);
            $this->configRepository->save($config);
        }

        $entry = new ActivityLog(
            ActivityLog::TYPE_SYSTEM,
            'Globale Spotify-App-Konfiguration validiert: ' . ($valid ? 'OK' : 'Fehler'),
            $valid ? ActivityLog::SEVERITY_INFO : ActivityLog::SEVERITY_WARNING,
            null,
            null,
            null,
            ['valid' => $valid, 'note' => $note],
        );
        $this->activityLog->append($entry);

        return [
            'valid' => $valid,
            'status' => $valid ? SpotifyAppConfiguration::STATUS_VALIDATED : SpotifyAppConfiguration::STATUS_ERROR,
            'note' => $note,
        ];
    }
}

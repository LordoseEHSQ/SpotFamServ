<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyAppConfigRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyCredentialsProviderInterface;
use App\Module\Spotify\Domain\Exception\SpotifyDomainException;
use App\Module\Spotify\Domain\SpotifyAppConfiguration;

/**
 * Prüft die effektiven Spotify-App-Credentials REAL gegen Spotify (client_credentials-Grant),
 * statt nur deren Vorhandensein zu prüfen. So beweist der "Validieren"-Button, dass
 * Client ID + Secret tatsächlich für die App gültig sind.
 */
final readonly class ValidateSpotifyAppConfig
{
    public function __construct(
        private SpotifyAppConfigRepositoryInterface $configRepository,
        private ActivityLogRepositoryInterface $activityLog,
        private SpotifyCredentialsProviderInterface $credentials,
        private SpotifyApiClientInterface $apiClient,
    ) {
    }

    /**
     * @return array{valid: bool, status: string, note: string}
     */
    public function __invoke(): array
    {
        $creds = $this->credentials->current();

        $valid = false;
        if (!$creds->isComplete()) {
            $note = 'Konfiguration unvollständig: Client ID, Client Secret oder Redirect URI fehlt.';
        } else {
            try {
                $this->apiClient->checkClientCredentials($creds->clientId, $creds->clientSecret);
                $valid = true;
                $note = sprintf('Credentials von Spotify bestätigt (Quelle: %s).', $creds->source);
            } catch (SpotifyDomainException $e) {
                $note = 'Spotify lehnt die Credentials ab: ' . $e->getMessage();
            }
        }

        $config = $this->configRepository->findActive();
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
            ['valid' => $valid, 'note' => $note, 'source' => $creds->source],
        );
        $this->activityLog->append($entry);

        return [
            'valid' => $valid,
            'status' => $valid ? SpotifyAppConfiguration::STATUS_VALIDATED : SpotifyAppConfiguration::STATUS_ERROR,
            'note' => $note,
        ];
    }
}

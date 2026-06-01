<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Spotify;

use App\Module\Spotify\Application\Dto\SpotifyCredentials;
use App\Module\Spotify\Application\Port\SpotifyAppConfigRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyCredentialsProviderInterface;

/**
 * Auflösung der effektiven Spotify-Credentials.
 *
 * Präzedenz (Entscheidung D-011): die aktive DB-Konfiguration gewinnt, ABER nur wenn sie
 * vollständig ist (Client ID + Secret + Redirect). Andernfalls wird ganzheitlich auf die
 * env-Werte zurückgefallen – kein Vermischen von DB- und env-Feldern, um zu verhindern,
 * dass eine neue Client ID mit einem alten Secret kombiniert wird.
 *
 * Es wird bewusst NICHT prozessweit gecacht: ein UI-Save soll ohne Neustart sofort greifen.
 */
final readonly class SpotifyCredentialsProvider implements SpotifyCredentialsProviderInterface
{
    /**
     * Kanonische OAuth-Scopes (code-seitig, Entscheidung D-011). Bewusst nicht über die UI
     * editierbar, da falsche Scopes Playback/Playlist-Funktionen still brechen würden.
     *
     * @var list<string>
     */
    public const DEFAULT_SCOPES = [
        'user-read-private',
        'user-read-email',
        'playlist-read-private',
        'playlist-read-collaborative',
        'playlist-modify-public',
        'playlist-modify-private',
        'user-modify-playback-state',
        'user-read-playback-state',
    ];

    public function __construct(
        private SpotifyAppConfigRepositoryInterface $configRepository,
        private string $envClientId,
        private string $envClientSecret,
        private string $envRedirectUri,
    ) {
    }

    public function current(): SpotifyCredentials
    {
        $config = $this->configRepository->findActive();

        if ($config !== null && $config->isComplete()) {
            return new SpotifyCredentials(
                (string) $config->getSpotifyClientId(),
                (string) $config->getSpotifyClientSecret(),
                (string) $config->getRedirectUri(),
                self::DEFAULT_SCOPES,
                'db',
            );
        }

        return new SpotifyCredentials(
            $this->envClientId,
            $this->envClientSecret,
            $this->envRedirectUri,
            self::DEFAULT_SCOPES,
            'env',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Port;

use App\Module\Spotify\Application\Dto\SpotifyCredentials;

/**
 * Liefert die zur Laufzeit gültigen Spotify-App-Credentials.
 * Single Source of Truth ist die DB-Konfiguration (SpotifyAppConfiguration);
 * env-Variablen dienen nur als Fallback (Bootstrap/Dev).
 */
interface SpotifyCredentialsProviderInterface
{
    public function current(): SpotifyCredentials;
}

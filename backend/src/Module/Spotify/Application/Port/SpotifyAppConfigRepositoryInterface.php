<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Port;

use App\Module\Spotify\Domain\SpotifyAppConfiguration;

/**
 * Repository für die systemweite Spotify-App-Konfiguration.
 * Singleton-Semantik: findActive() gibt den einzig aktiven Datensatz zurück.
 */
interface SpotifyAppConfigRepositoryInterface
{
    public function findActive(): ?SpotifyAppConfiguration;

    public function save(SpotifyAppConfiguration $config): void;
}

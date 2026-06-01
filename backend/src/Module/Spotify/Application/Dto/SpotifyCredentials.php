<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Dto;

/**
 * Effektive Spotify-App-Credentials zur Laufzeit (aufgelöst aus DB-Config oder env).
 * Reines Wert-Objekt; das Secret wird hier im Klartext gehalten und darf NIE geloggt
 * oder über eine API zurückgegeben werden.
 */
final readonly class SpotifyCredentials
{
    /**
     * @param list<string> $scopes
     * @param 'db'|'env'   $source
     */
    public function __construct(
        public string $clientId,
        public string $clientSecret,
        public string $redirectUri,
        public array $scopes,
        public string $source,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->redirectUri !== '';
    }
}

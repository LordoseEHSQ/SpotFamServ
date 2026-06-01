<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Spotify;

use App\Module\Spotify\Application\Dto\SpotifyDeviceDto;
use App\Module\Spotify\Application\Dto\SpotifyPlaybackStateDto;
use App\Module\Spotify\Application\Dto\SpotifyPlaylistDto;
use App\Module\Spotify\Application\Dto\SpotifyPlaylistTracksDto;
use App\Module\Spotify\Application\Dto\SpotifySearchResultDto;
use App\Module\Spotify\Application\Dto\SpotifyTokenResponseDto;
use App\Module\Spotify\Application\Dto\SpotifyTrackDto;
use App\Module\Spotify\Application\Dto\SpotifyUserDto;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyCredentialsProviderInterface;
use App\Module\Spotify\Domain\Exception\SpotifyApiException;
use App\Module\Spotify\Domain\Exception\SpotifyNoDeviceException;
use App\Module\Spotify\Domain\Exception\SpotifyScopeMissingException;
use App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for Spotify OAuth and Web API. Maps Spotify errors to domain exceptions.
 */
final class SpotifyHttpApiClient implements SpotifyApiClientInterface
{
    private const TOKEN_URL = 'https://accounts.spotify.com/api/token';
    private const API_BASE = 'https://api.spotify.com/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SpotifyCredentialsProviderInterface $credentials,
    ) {
    }

    public function checkClientCredentials(string $clientId, string $clientSecret): void
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => ['grant_type' => 'client_credentials'],
            'auth_basic' => [$clientId, $clientSecret],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $body = $response->toArray(false);
            $detail = $body['error_description'] ?? $body['error'] ?? ('HTTP ' . $status);
            $message = is_string($detail) && $detail !== '' ? $detail : 'Ungültige Client-Credentials';
            if ($status === 400 || $status === 401) {
                throw new SpotifyTokenInvalidException($message);
            }
            throw new SpotifyApiException('Spotify credential check failed: ' . $message, $status);
        }
    }

    public function exchangeCode(string $code, string $redirectUri): SpotifyTokenResponseDto
    {
        $creds = $this->credentials->current();
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ],
            'auth_basic' => [$creds->clientId, $creds->clientSecret],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);
        return $this->parseTokenResponse($response, false, $code);
    }

    public function refreshToken(string $refreshToken): SpotifyTokenResponseDto
    {
        $creds = $this->credentials->current();
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ],
            'auth_basic' => [$creds->clientId, $creds->clientSecret],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);
        return $this->parseTokenResponse($response, true, $refreshToken);
    }

    public function getCurrentUser(string $accessToken): SpotifyUserDto
    {
        $data = $this->get(self::API_BASE . '/me', $accessToken);
        return new SpotifyUserDto(
            (string) ($data['id'] ?? ''),
            (string) ($data['display_name'] ?? $data['id'] ?? ''),
        );
    }

    public function getUserPlaylists(string $accessToken, int $offset = 0, int $limit = 50): array
    {
        $data = $this->get(self::API_BASE . '/me/playlists', $accessToken, ['offset' => $offset, 'limit' => $limit]);
        $items = $data['items'] ?? [];
        $out = [];
        foreach ($items as $item) {
            $out[] = new SpotifyPlaylistDto(
                (string) ($item['id'] ?? ''),
                (string) ($item['name'] ?? ''),
                (string) ($item['uri'] ?? 'spotify:playlist:' . ($item['id'] ?? '')),
                isset($item['owner']['id']) ? (string) $item['owner']['id'] : null,
            );
        }
        return $out;
    }

    public function search(string $accessToken, string $query, string $types = 'playlist,track'): SpotifySearchResultDto
    {
        $data = $this->get(self::API_BASE . '/search', $accessToken, [
            'q'      => $query,
            'type'   => $types,
            'limit'  => '10',
            'market' => 'from_token',
        ]);
        $playlists = [];
        foreach (($data['playlists']['items'] ?? []) as $item) {
            if ($item === null) {
                continue;
            }
            $playlists[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'uri' => (string) ($item['uri'] ?? ''),
                'type' => 'playlist',
            ];
        }
        $tracks = [];
        foreach (($data['tracks']['items'] ?? []) as $item) {
            if ($item === null) {
                continue;
            }
            $artists = array_map(
                fn ($a) => (string) ($a['name'] ?? ''),
                $item['artists'] ?? []
            );
            $coverUrl = null;
            $images = $item['album']['images'] ?? [];
            if (!empty($images)) {
                $coverUrl = (string) ($images[0]['url'] ?? '');
            }
            $tracks[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'uri' => (string) ($item['uri'] ?? ''),
                'artists' => $artists,
                'album_name' => (string) ($item['album']['name'] ?? ''),
                'album_cover_url' => $coverUrl,
                'duration_ms' => (int) ($item['duration_ms'] ?? 0),
                'type' => 'track',
            ];
        }
        return new SpotifySearchResultDto($playlists, $tracks);
    }

    public function getAvailableDevices(string $accessToken): array
    {
        $data = $this->get(self::API_BASE . '/me/player/devices', $accessToken);
        $items = $data['devices'] ?? [];
        $out = [];
        foreach ($items as $item) {
            $out[] = new SpotifyDeviceDto(
                (string) ($item['id'] ?? ''),
                (string) ($item['name'] ?? ''),
                (string) ($item['type'] ?? 'Unknown'),
                (bool) ($item['is_active'] ?? false),
            );
        }
        return $out;
    }

    public function transferPlayback(string $accessToken, string $deviceId): void
    {
        $this->put(self::API_BASE . '/me/player', $accessToken, ['device_ids' => [$deviceId], 'play' => false]);
    }

    public function startPlayback(string $accessToken, string $contextUri, ?string $deviceId = null): void
    {
        $body = ['context_uri' => $contextUri];
        if ($deviceId !== null) {
            $body['device_id'] = $deviceId;
        }
        $this->put(self::API_BASE . '/me/player/play', $accessToken, $body);
    }

    public function getCurrentPlayback(string $accessToken): ?SpotifyPlaybackStateDto
    {
        $response = $this->httpClient->request('GET', self::API_BASE . '/me/player', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            'query' => ['additional_types' => 'track'],
        ]);
        $status = $response->getStatusCode();
        if ($status === 204 || $status === 202) {
            return null;
        }
        $data = $this->decodeAndMapErrors($response, '/me/player');
        if (empty($data) || !isset($data['is_playing'])) {
            return null;
        }

        $track = null;
        $item = $data['item'] ?? null;
        if ($item !== null && ($item['type'] ?? '') === 'track') {
            $artists = array_map(fn ($a) => (string) ($a['name'] ?? ''), $item['artists'] ?? []);
            $images = $item['album']['images'] ?? [];
            $coverUrl = !empty($images) ? (string) ($images[0]['url'] ?? '') : null;
            $track = new SpotifyTrackDto(
                (string) ($item['id'] ?? ''),
                (string) ($item['name'] ?? ''),
                (string) ($item['uri'] ?? ''),
                $artists,
                (string) ($item['album']['name'] ?? '') ?: null,
                $coverUrl,
                (int) ($item['duration_ms'] ?? 0),
            );
        }

        $device = $data['device'] ?? [];

        return new SpotifyPlaybackStateDto(
            (bool) ($data['is_playing'] ?? false),
            (int) ($data['progress_ms'] ?? 0),
            $track,
            isset($device['id']) ? (string) $device['id'] : null,
            isset($device['name']) ? (string) $device['name'] : null,
            isset($device['type']) ? (string) $device['type'] : null,
            isset($data['context']['uri']) ? (string) $data['context']['uri'] : null,
            isset($data['context']['type']) ? (string) $data['context']['type'] : null,
            (int) ($device['volume_percent'] ?? 0),
        );
    }

    public function pausePlayback(string $accessToken, ?string $deviceId = null): void
    {
        $query = $deviceId !== null ? ['device_id' => $deviceId] : [];
        $response = $this->httpClient->request('PUT', self::API_BASE . '/me/player/pause', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
            'query' => $query,
            'body' => '',
        ]);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $body = $response->toArray(false);
            $err = $body['error'] ?? [];
            $message = is_array($err) ? ($err['message'] ?? (string) $status) : (string) $err;
            if ($status === 403 && str_contains(strtolower($message), 'premium')) {
                throw new SpotifyScopeMissingException('Spotify Premium required for playback control');
            }
            if ($status !== 403) {
                throw new SpotifyApiException('Pause failed: ' . $message, $status);
            }
        }
    }

    public function nextTrack(string $accessToken, ?string $deviceId = null): void
    {
        $query = $deviceId !== null ? ['device_id' => $deviceId] : [];
        $response = $this->httpClient->request('POST', self::API_BASE . '/me/player/next', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
            'query' => $query,
            'body' => '',
        ]);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $body = $response->toArray(false);
            $err = $body['error'] ?? [];
            $message = is_array($err) ? ($err['message'] ?? (string) $status) : (string) $err;
            throw new SpotifyApiException('Next track failed: ' . $message, $status);
        }
    }

    public function previousTrack(string $accessToken, ?string $deviceId = null): void
    {
        $response = $this->httpClient->request('POST', self::API_BASE . '/me/player/previous', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
            'body' => '',
        ]);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $body = $response->toArray(false);
            $err = $body['error'] ?? [];
            $message = is_array($err) ? ($err['message'] ?? (string) $status) : (string) $err;
            throw new SpotifyApiException('Previous track failed: ' . $message, $status);
        }
    }

    public function getPlaylistTracks(string $accessToken, string $playlistId, int $offset = 0, int $limit = 50): SpotifyPlaylistTracksDto
    {
        $data = $this->get(self::API_BASE . '/playlists/' . $playlistId . '/tracks', $accessToken, [
            'offset' => (string) $offset,
            'limit' => (string) $limit,
            'additional_types' => 'track,episode',
        ]);

        $items = [];
        foreach (($data['items'] ?? []) as $entry) {
            $item = $entry['track'] ?? null;
            // Skip null entries, local files, podcast episodes (no id or type != track)
            if ($item === null || ($item['id'] ?? '') === '' || ($item['type'] ?? 'track') !== 'track') {
                continue;
            }
            $artists = array_map(fn ($a) => (string) ($a['name'] ?? ''), $item['artists'] ?? []);
            $images = $item['album']['images'] ?? [];
            $coverUrl = !empty($images) ? (string) ($images[0]['url'] ?? '') : null;
            $items[] = new SpotifyTrackDto(
                (string) ($item['id'] ?? ''),
                (string) ($item['name'] ?? ''),
                (string) ($item['uri'] ?? ''),
                $artists,
                (string) ($item['album']['name'] ?? '') ?: null,
                $coverUrl,
                (int) ($item['duration_ms'] ?? 0),
            );
        }

        return new SpotifyPlaylistTracksDto(
            $items,
            (int) ($data['total'] ?? 0),
            (int) ($data['offset'] ?? $offset),
            (int) ($data['limit'] ?? $limit),
        );
    }

    public function createPlaylist(string $accessToken, string $spotifyUserId, string $name, ?string $description = null): SpotifyPlaylistDto
    {
        $body = ['name' => $name, 'public' => false];
        if ($description !== null && $description !== '') {
            $body['description'] = $description;
        }
        $response = $this->httpClient->request('POST', self::API_BASE . '/users/' . $spotifyUserId . '/playlists', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
            'json' => $body,
        ]);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $b = $response->toArray(false);
            $err = $b['error'] ?? [];
            $message = is_array($err) ? ($err['message'] ?? (string) $status) : (string) $err;
            if ($status === 403) {
                throw new SpotifyScopeMissingException(
                    'Fehlende Berechtigung zum Erstellen von Playlists. Bitte Spotify-Verbindung neu autorisieren (Scope: playlist-modify-private).'
                );
            }
            throw new SpotifyApiException('Create playlist failed: ' . $message, $status);
        }
        $data = $response->toArray(false);
        return new SpotifyPlaylistDto(
            (string) ($data['id'] ?? ''),
            (string) ($data['name'] ?? $name),
            (string) ($data['uri'] ?? ''),
            isset($data['owner']['id']) ? (string) $data['owner']['id'] : null,
        );
    }

    public function addTracksToPlaylist(string $accessToken, string $playlistId, array $uris): void
    {
        $response = $this->httpClient->request('POST', self::API_BASE . '/playlists/' . $playlistId . '/tracks', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
            'json' => ['uris' => $uris],
        ]);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $b = $response->toArray(false);
            $err = $b['error'] ?? [];
            $message = is_array($err) ? ($err['message'] ?? (string) $status) : (string) $err;
            if ($status === 403) {
                throw new SpotifyScopeMissingException(
                    'Fehlende Berechtigung zum Bearbeiten von Playlists. Bitte Spotify-Verbindung neu autorisieren.'
                );
            }
            throw new SpotifyApiException('Add tracks failed: ' . $message, $status);
        }
    }

    private function parseTokenResponse($response, bool $isRefresh, string $currentRefreshToken): SpotifyTokenResponseDto
    {
        $status = $response->getStatusCode();
        $body = $response->toArray(false);
        if ($status >= 400) {
            $detail = $body['error_description'] ?? $body['error'] ?? 'Token request failed';
            if ($status === 401 || ($body['error'] ?? '') === 'invalid_grant') {
                throw new SpotifyTokenInvalidException($detail);
            }
            if ($status === 403) {
                throw new SpotifyScopeMissingException($detail);
            }
            throw new SpotifyApiException('Spotify OAuth error: ' . $detail, $status);
        }
        $scope = $body['scope'] ?? '';
        $expiresIn = (int) ($body['expires_in'] ?? 3600);
        $refresh = $body['refresh_token'] ?? ($isRefresh ? $currentRefreshToken : '');
        return new SpotifyTokenResponseDto(
            (string) ($body['access_token'] ?? ''),
            (string) $refresh,
            $expiresIn,
            $scope,
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    private function get(string $url, string $accessToken, array $query = []): array
    {
        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            'query' => $query,
        ]);
        return $this->decodeAndMapErrors($response, $url);
    }

    /**
     * @param array<string, mixed> $json
     */
    private function put(string $url, string $accessToken, array $json): void
    {
        $response = $this->httpClient->request('PUT', $url, [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
            'json' => $json,
        ]);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $message = $this->extractErrorMessage($response, $status);
            if ($status === 404 && str_contains(strtolower($message), 'device')) {
                throw new SpotifyNoDeviceException($message);
            }
            if ($status === 401) {
                throw new SpotifyTokenInvalidException($message);
            }
            if ($status === 403 && str_contains(strtolower($message), 'not registered for this application')) {
                throw new SpotifyScopeMissingException(
                    'Dieses Spotify-Konto ist nicht für die App freigeschaltet (Spotify Dashboard → User Management).'
                );
            }
            throw new SpotifyApiException('Spotify API error: ' . $message, $status);
        }
    }

    private function decodeAndMapErrors($response, string $_context): array
    {
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $message = $this->extractErrorMessage($response, $status);
            if ($status === 401) {
                throw new SpotifyTokenInvalidException($message);
            }
            if ($status === 403) {
                $lower = strtolower($message);
                // App im Development Mode: Konto nicht freigeschaltet (User Management).
                if (str_contains($lower, 'not registered for this application')) {
                    throw new SpotifyScopeMissingException(
                        'Dieses Spotify-Konto ist nicht für die App freigeschaltet. '
                        . 'Bitte im Spotify Dashboard unter "User Management" mit Name + E-Mail hinzufügen '
                        . '(App läuft im Development Mode).'
                    );
                }
                // Spotify returns 403 for two distinct reasons:
                // "Insufficient client scope" = missing OAuth scope
                // "Forbidden" = resource access denied (collaborative/private playlist owned by other user)
                if (str_contains($lower, 'scope') || str_contains($lower, 'insufficient')) {
                    throw new SpotifyScopeMissingException($message);
                }
                throw new SpotifyApiException('Zugriff verweigert: ' . $message, 403);
            }
            throw new SpotifyApiException('Spotify API error: ' . $message, $status);
        }
        return $response->toArray(false);
    }

    /**
     * Liest die Fehlermeldung robust aus – auch wenn Spotify (z. B. bei 403 im
     * Development Mode) eine reine Text-Antwort statt JSON liefert. Vermeidet, dass
     * eine nicht-JSON-Antwort als JsonException/500 durchschlägt.
     */
    private function extractErrorMessage($response, int $status): string
    {
        try {
            $raw = $response->getContent(false);
        } catch (\Throwable) {
            return 'Spotify request failed (HTTP ' . $status . ')';
        }
        $raw = trim($raw);
        if ($raw === '') {
            return 'HTTP ' . $status;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $err = $decoded['error'] ?? null;
            if (is_array($err)) {
                return (string) ($err['message'] ?? $status);
            }
            if (is_string($err) && $err !== '') {
                return $decoded['error_description'] ?? $err;
            }
        }
        return $raw;
    }
}

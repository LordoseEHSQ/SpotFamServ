<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Spotify;

use App\Module\Spotify\Application\Dto\SpotifyDeviceDto;
use App\Module\Spotify\Application\Dto\SpotifyPlaylistDto;
use App\Module\Spotify\Application\Dto\SpotifySearchResultDto;
use App\Module\Spotify\Application\Dto\SpotifyTokenResponseDto;
use App\Module\Spotify\Application\Dto\SpotifyUserDto;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Domain\Exception\SpotifyException;
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
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function exchangeCode(string $code, string $redirectUri): SpotifyTokenResponseDto
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ],
            'auth_basic' => [$this->clientId, $this->clientSecret],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);
        return $this->parseTokenResponse($response, false, $code);
    }

    public function refreshToken(string $refreshToken): SpotifyTokenResponseDto
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ],
            'auth_basic' => [$this->clientId, $this->clientSecret],
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
            'q' => $query,
            'type' => $types,
            'limit' => 20,
        ]);
        $playlists = [];
        foreach (($data['playlists']['items'] ?? []) as $item) {
            $playlists[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'uri' => (string) ($item['uri'] ?? ''),
                'type' => 'playlist',
            ];
        }
        return new SpotifySearchResultDto($playlists);
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
            throw new SpotifyException('Spotify OAuth error: ' . $detail, $status);
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
            $body = $response->toArray(false);
            $err = $body['error'] ?? [];
            $message = is_array($err) ? ($err['message'] ?? (string) $status) : (string) $err;
            if ($status === 404 && str_contains(strtolower($message), 'device')) {
                throw new \App\Module\Spotify\Domain\Exception\SpotifyNoDeviceException($message);
            }
            if ($status === 401) {
                throw new SpotifyTokenInvalidException($message);
            }
            throw new SpotifyException('Spotify API error: ' . $message, $status);
        }
    }

    private function decodeAndMapErrors($response, string $_context): array
    {
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $body = $response->toArray(false);
            $err = $body['error'] ?? [];
            $message = is_array($err) ? ($err['message'] ?? (string) $status) : (string) $err;
            if ($status === 401) {
                throw new SpotifyTokenInvalidException($message);
            }
            if ($status === 403) {
                throw new SpotifyScopeMissingException($message);
            }
            throw new SpotifyException('Spotify API error: ' . $message, $status);
        }
        return $response->toArray(false);
    }
}

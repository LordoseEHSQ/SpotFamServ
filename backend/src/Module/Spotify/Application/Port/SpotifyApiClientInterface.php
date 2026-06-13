<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Port;

use App\Module\Spotify\Application\Dto\SpotifyDeviceDto;
use App\Module\Spotify\Application\Dto\SpotifyPlaybackStateDto;
use App\Module\Spotify\Application\Dto\SpotifyPlaylistDto;
use App\Module\Spotify\Application\Dto\SpotifyPlaylistTracksDto;
use App\Module\Spotify\Application\Dto\SpotifySearchResultDto;
use App\Module\Spotify\Application\Dto\SpotifyTokenResponseDto;
use App\Module\Spotify\Application\Dto\SpotifyUserDto;

/**
 * Port for all HTTP calls to Spotify (OAuth + Web API).
 * Implementations must handle only HTTP; token storage/refresh is outside.
 */
interface SpotifyApiClientInterface
{
    /**
     * Verify app client credentials against Spotify via the client_credentials grant.
     * Does not require a user token; proves that client id + secret are valid for the app.
     *
     * @throws \App\Module\Spotify\Domain\Exception\SpotifyDomainException when Spotify rejects the credentials
     */
    public function checkClientCredentials(string $clientId, string $clientSecret): void;

    /**
     * Exchange authorization code for tokens.
     *
     * @throws \App\Module\Spotify\Domain\Exception\SpotifyException on API error
     */
    public function exchangeCode(string $code, string $redirectUri): SpotifyTokenResponseDto;

    /**
     * Refresh access token using refresh token.
     *
     * @throws \App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException on invalid/expired refresh
     */
    public function refreshToken(string $refreshToken): SpotifyTokenResponseDto;

    /**
     * Get current user (/me). Used for validation.
     *
     * @throws \App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException on 401
     */
    public function getCurrentUser(string $accessToken): SpotifyUserDto;

    /**
     * Get user's playlists (paginated; offset 0, limit 50 for MVP).
     *
     * @return list<SpotifyPlaylistDto>
     */
    public function getUserPlaylists(string $accessToken, int $offset = 0, int $limit = 50): array;

    /**
     * Search: q and types (e.g. playlist,track). Returns playlists (and optionally more).
     */
    public function search(string $accessToken, string $query, string $types = 'playlist,track'): SpotifySearchResultDto;

    /**
     * Get available devices.
     *
     * @return list<SpotifyDeviceDto>
     */
    public function getAvailableDevices(string $accessToken): array;

    /**
     * Transfer playback to device (required before start).
     */
    public function transferPlayback(string $accessToken, string $deviceId): void;

    /**
     * Start playback (context = playlist URI). Device must be active or transfer first.
     */
    public function startPlayback(string $accessToken, string $contextUri, ?string $deviceId = null): void;

    /**
     * Get current playback state. Returns null if nothing is playing.
     */
    public function getCurrentPlayback(string $accessToken): ?SpotifyPlaybackStateDto;

    /**
     * Pause current playback.
     */
    public function pausePlayback(string $accessToken, ?string $deviceId = null): void;

    /**
     * Skip to next track.
     */
    public function nextTrack(string $accessToken, ?string $deviceId = null): void;

    /**
     * Skip to previous track.
     */
    public function previousTrack(string $accessToken, ?string $deviceId = null): void;

    /**
     * Get tracks of a playlist (paginated).
     */
    public function getPlaylistTracks(string $accessToken, string $playlistId, int $offset = 0, int $limit = 50): SpotifyPlaylistTracksDto;

    /**
     * Create a new playlist for a Spotify user.
     */
    public function createPlaylist(string $accessToken, string $spotifyUserId, string $name, ?string $description = null): SpotifyPlaylistDto;

    /**
     * Add tracks to a playlist.
     *
     * @param list<string> $uris Spotify track URIs
     */
    public function addTracksToPlaylist(string $accessToken, string $playlistId, array $uris): void;

    /**
     * Set playback volume (0–100). Silently ignored when no active device.
     */
    public function setVolume(string $accessToken, int $volumePercent, ?string $deviceId = null): void;
}

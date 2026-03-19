<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Dto;

/**
 * Search result: playlists and tracks.
 *
 * @phpstan-type SearchItem array{id: string, name: string, uri: string, type: string}
 * @phpstan-type TrackItem array{id: string, name: string, uri: string, artists: list<string>, album_name: string|null, album_cover_url: string|null, duration_ms: int, type: string}
 */
final readonly class SpotifySearchResultDto
{
    /**
     * @param list<SearchItem> $playlists
     * @param list<TrackItem>  $tracks
     */
    public function __construct(
        public array $playlists,
        public array $tracks = [],
    ) {
    }
}

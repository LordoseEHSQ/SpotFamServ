<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Dto;

/**
 * Search result: playlists (and optionally tracks/albums later).
 *
 * @phpstan-type SearchItem array{id: string, name: string, uri: string, type: string}
 */
final readonly class SpotifySearchResultDto
{
    /** @param list<SearchItem> $playlists */
    public function __construct(
        public array $playlists,
    ) {
    }
}

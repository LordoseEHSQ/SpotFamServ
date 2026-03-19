<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Dto;

final readonly class SpotifyPlaylistTracksDto
{
    /**
     * @param list<SpotifyTrackDto> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $offset,
        public int $limit,
    ) {
    }
}

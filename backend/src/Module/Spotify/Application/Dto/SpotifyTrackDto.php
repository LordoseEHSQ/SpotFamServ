<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Dto;

final readonly class SpotifyTrackDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $uri,
        /** @var list<string> */
        public array $artists,
        public ?string $albumName,
        public ?string $albumCoverUrl,
        public int $durationMs,
    ) {
    }
}

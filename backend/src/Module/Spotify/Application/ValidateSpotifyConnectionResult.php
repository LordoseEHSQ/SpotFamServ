<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

final readonly class ValidateSpotifyConnectionResult
{
    public function __construct(
        public bool $valid,
        public string $spotifyUserId,
        public string $displayName,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Dto;

/**
 * Minimal user info from Spotify /me.
 */
final readonly class SpotifyUserDto
{
    public function __construct(
        public string $id,
        public string $displayName,
    ) {
    }
}

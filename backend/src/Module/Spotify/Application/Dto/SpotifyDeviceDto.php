<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Dto;

final readonly class SpotifyDeviceDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public bool $isActive,
    ) {
    }
}

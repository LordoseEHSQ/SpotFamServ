<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Dto;

final readonly class SpotifyPlaybackStateDto
{
    public function __construct(
        public bool $isPlaying,
        public int $progressMs,
        public ?SpotifyTrackDto $currentTrack,
        public ?string $deviceId,
        public ?string $deviceName,
        public ?string $deviceType,
        public ?string $contextUri,
        public ?string $contextType,
        public int $volumePercent,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

final readonly class GetSpotifyStatusResult
{
    public function __construct(
        public string $status,
        public ?string $linkId,
    ) {
    }
}

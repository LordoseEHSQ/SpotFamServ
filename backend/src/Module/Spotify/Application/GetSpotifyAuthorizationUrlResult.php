<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

final readonly class GetSpotifyAuthorizationUrlResult
{
    public function __construct(
        public string $authorizationUrl,
        public string $state,
        public string $redirectUri,
    ) {
    }
}

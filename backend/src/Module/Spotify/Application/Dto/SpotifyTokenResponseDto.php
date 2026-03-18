<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Dto;

/**
 * Internal DTO for token exchange/refresh response (from Spotify).
 */
final readonly class SpotifyTokenResponseDto
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
        public string $scope,
    ) {
    }
}

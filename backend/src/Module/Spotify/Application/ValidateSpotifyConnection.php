<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;

/**
 * Validates that the profile's Spotify link is working (token valid or refreshed, /me succeeds).
 */
final readonly class ValidateSpotifyConnection
{
    public function __construct(
        private SpotifyTokenManagerInterface $tokenManager,
        private SpotifyApiClientInterface $apiClient,
    ) {
    }

    public function __invoke(string $profileId): ValidateSpotifyConnectionResult
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        $user = $this->apiClient->getCurrentUser($link->getAccessToken());
        return new ValidateSpotifyConnectionResult(true, $user->id, $user->displayName);
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Port\SpotifyAccountLinkRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyApiClientInterface;
use App\Module\Spotify\Application\Port\SpotifyTokenManagerInterface;

/**
 * Validates that the profile's Spotify link is working (token valid or refreshed, /me succeeds).
 * On success: persists display_name and last_validated_at on the link.
 */
final class ValidateSpotifyConnection
{
    public function __construct(
        private readonly SpotifyTokenManagerInterface $tokenManager,
        private readonly SpotifyApiClientInterface $apiClient,
        private readonly SpotifyAccountLinkRepositoryInterface $linkRepository,
    ) {
    }

    public function __invoke(string $profileId): ValidateSpotifyConnectionResult
    {
        $link = $this->tokenManager->getValidLinkForProfile($profileId);
        $user = $this->apiClient->getCurrentUser($link->getAccessToken());

        $link->markValidated($user->displayName);
        $this->linkRepository->save($link);

        return new ValidateSpotifyConnectionResult(true, $user->id, $user->displayName);
    }
}

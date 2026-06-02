<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Port\SpotifyAccountLinkRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyAccountLink;

/**
 * Single source of truth for a profile's Spotify connection status.
 *
 * Status is driven by the refresh-token validity, NOT by the short-lived access-token clock:
 * the access token (1h) is auto-refreshed by SpotifyTokenManager, so an expired access token
 * is invisible to the user. Only a persisted re-auth requirement downgrades the status (#25, D-014).
 */
final readonly class GetSpotifyStatus
{
    public function __construct(
        private SpotifyAccountLinkRepositoryInterface $repository,
    ) {
    }

    public function __invoke(string $profileId): GetSpotifyStatusResult
    {
        $link = $this->repository->findByProfileId($profileId);
        return new GetSpotifyStatusResult(self::resolve($link), $link?->getId());
    }

    /**
     * Pure status mapping, reused by FamilyProfileController to avoid duplicated/divergent logic.
     *
     * @return 'connected'|'reauth_required'|'not_connected'
     */
    public static function resolve(?SpotifyAccountLink $link): string
    {
        if ($link === null) {
            return 'not_connected';
        }
        if ($link->needsReauth()) {
            return 'reauth_required';
        }
        return 'connected';
    }
}

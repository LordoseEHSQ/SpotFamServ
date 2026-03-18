<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Port\SpotifyAccountLinkRepositoryInterface;

/**
 * Returns connection status for a profile's Spotify link (connected / not_found / expired).
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
        if ($link === null) {
            return new GetSpotifyStatusResult('not_connected', null);
        }
        $expired = $link->getExpiresAt() < new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return new GetSpotifyStatusResult($expired ? 'expired' : 'connected', $link->getId());
    }
}

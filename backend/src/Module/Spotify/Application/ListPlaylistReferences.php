<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\Spotify\Application\Port\SpotifyPlaylistReferenceRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyPlaylistReference;

final readonly class ListPlaylistReferences
{
    public function __construct(
        private SpotifyPlaylistReferenceRepositoryInterface $playlistRefRepository,
    ) {
    }

    /** @return list<SpotifyPlaylistReference> */
    public function __invoke(string $profileId): array
    {
        return $this->playlistRefRepository->findByProfileId($profileId);
    }
}

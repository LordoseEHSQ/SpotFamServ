<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Port;

use App\Module\Spotify\Domain\SpotifyPlaylistReference;

interface SpotifyPlaylistReferenceRepositoryInterface
{
    public function findById(string $id): ?SpotifyPlaylistReference;

    /** @return list<SpotifyPlaylistReference> */
    public function findByProfileId(string $profileId): array;

    public function findByIdAndProfile(string $id, string $profileId): ?SpotifyPlaylistReference;

    public function save(SpotifyPlaylistReference $ref): void;
}

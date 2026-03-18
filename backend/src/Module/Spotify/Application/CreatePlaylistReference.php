<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyPlaylistReferenceRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyPlaylistReference;
use App\Shared\Application\Exception\NotFoundException;

final readonly class CreatePlaylistReference
{
    public function __construct(
        private SpotifyPlaylistReferenceRepositoryInterface $playlistRefRepository,
        private FamilyProfileRepositoryInterface $profileRepository,
    ) {
    }

    public function __invoke(string $profileId, string $spotifyPlaylistId, string $name, ?string $ownerId = null): SpotifyPlaylistReference
    {
        $profile = $this->profileRepository->find($profileId);
        if ($profile === null) {
            throw new NotFoundException('Profile not found.');
        }
        $ref = new SpotifyPlaylistReference($profileId, $spotifyPlaylistId, $name, $ownerId);
        $this->playlistRefRepository->save($ref);
        return $ref;
    }
}

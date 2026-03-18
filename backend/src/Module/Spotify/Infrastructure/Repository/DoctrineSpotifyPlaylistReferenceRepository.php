<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Repository;

use App\Module\Spotify\Application\Port\SpotifyPlaylistReferenceRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyPlaylistReference;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSpotifyPlaylistReferenceRepository implements SpotifyPlaylistReferenceRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findById(string $id): ?SpotifyPlaylistReference
    {
        return $this->em->find(SpotifyPlaylistReference::class, $id);
    }

    /** @return list<SpotifyPlaylistReference> */
    public function findByProfileId(string $profileId): array
    {
        return $this->em->getRepository(SpotifyPlaylistReference::class)->findBy(
            ['familyProfileId' => $profileId],
            ['name' => 'ASC'],
        );
    }

    public function findByIdAndProfile(string $id, string $profileId): ?SpotifyPlaylistReference
    {
        return $this->em->getRepository(SpotifyPlaylistReference::class)->findOneBy(
            ['id' => $id, 'familyProfileId' => $profileId],
        );
    }

    public function save(SpotifyPlaylistReference $ref): void
    {
        $this->em->persist($ref);
        $this->em->flush();
    }
}

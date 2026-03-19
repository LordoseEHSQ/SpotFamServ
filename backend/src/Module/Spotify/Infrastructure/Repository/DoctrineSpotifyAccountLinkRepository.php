<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Repository;

use App\Module\Spotify\Application\Port\SpotifyAccountLinkRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyAccountLink;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSpotifyAccountLinkRepository implements SpotifyAccountLinkRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findByProfileId(string $profileId): ?SpotifyAccountLink
    {
        return $this->em->getRepository(SpotifyAccountLink::class)->findOneBy(['familyProfileId' => $profileId]);
    }

    public function save(SpotifyAccountLink $link): void
    {
        $this->em->persist($link);
        $this->em->flush();
    }

    public function delete(SpotifyAccountLink $link): void
    {
        $this->em->remove($link);
        $this->em->flush();
    }
}

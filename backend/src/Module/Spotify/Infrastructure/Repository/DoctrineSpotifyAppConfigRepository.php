<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Repository;

use App\Module\Spotify\Application\Port\SpotifyAppConfigRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyAppConfiguration;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineSpotifyAppConfigRepository implements SpotifyAppConfigRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function findActive(): ?SpotifyAppConfiguration
    {
        return $this->em->getRepository(SpotifyAppConfiguration::class)
            ->findOneBy(['isActive' => true]);
    }

    public function save(SpotifyAppConfiguration $config): void
    {
        $this->em->persist($config);
        $this->em->flush();
    }
}

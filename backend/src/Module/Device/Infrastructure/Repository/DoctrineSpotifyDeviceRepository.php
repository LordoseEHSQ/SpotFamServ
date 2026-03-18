<?php

declare(strict_types=1);

namespace App\Module\Device\Infrastructure\Repository;

use App\Module\Device\Application\Port\SpotifyDeviceRepositoryInterface;
use App\Module\Device\Domain\SpotifyDevice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineSpotifyDeviceRepository implements SpotifyDeviceRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function findById(Uuid $id): ?SpotifyDevice
    {
        return $this->em->find(SpotifyDevice::class, $id);
    }

    public function findBySpotifyDeviceId(string $spotifyDeviceId): ?SpotifyDevice
    {
        return $this->em->getRepository(SpotifyDevice::class)
            ->findOneBy(['spotifyDeviceId' => $spotifyDeviceId]);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(SpotifyDevice::class)
            ->findBy([], ['spotifyDeviceName' => 'ASC']);
    }

    public function findByProfileId(Uuid $profileId): array
    {
        return $this->em->getRepository(SpotifyDevice::class)
            ->findBy(['assignedFamilyProfileId' => $profileId]);
    }

    public function save(SpotifyDevice $device): void
    {
        $this->em->persist($device);
        $this->em->flush();
    }

    public function count(): int
    {
        return (int) $this->em->getRepository(SpotifyDevice::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Device\Infrastructure\Repository;

use App\Module\Device\Application\Port\DeviceDiscoveryRunRepositoryInterface;
use App\Module\Device\Domain\DeviceDiscoveryRun;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineDeviceDiscoveryRunRepository implements DeviceDiscoveryRunRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function findById(Uuid $id): ?DeviceDiscoveryRun
    {
        return $this->em->find(DeviceDiscoveryRun::class, $id);
    }

    public function findLatest(): ?DeviceDiscoveryRun
    {
        return $this->em->getRepository(DeviceDiscoveryRun::class)
            ->createQueryBuilder('r')
            ->orderBy('r.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findRecent(int $limit = 10): array
    {
        return $this->em->getRepository(DeviceDiscoveryRun::class)
            ->createQueryBuilder('r')
            ->orderBy('r.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(DeviceDiscoveryRun $run): void
    {
        $this->em->persist($run);
        $this->em->flush();
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Infrastructure\Repository;

use App\Module\Provisioning\Application\Port\FlashJobRepositoryInterface;
use App\Module\Provisioning\Domain\FlashJob;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineFlashJobRepository implements FlashJobRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findById(string $id): ?FlashJob
    {
        return $this->em->getRepository(FlashJob::class)->find($id);
    }

    public function findOldestPendingForDevice(string $deviceId): ?FlashJob
    {
        return $this->em->createQueryBuilder()
            ->select('j')
            ->from(FlashJob::class, 'j')
            ->join('j.device', 'd')
            ->where('d.id = :deviceId')
            ->andWhere('j.status = :status')
            ->setParameter('deviceId', $deviceId)
            ->setParameter('status', FlashJob::STATUS_PENDING)
            ->orderBy('j.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveForDevice(string $deviceId): ?FlashJob
    {
        return $this->em->createQueryBuilder()
            ->select('j')
            ->from(FlashJob::class, 'j')
            ->join('j.device', 'd')
            ->where('d.id = :deviceId')
            ->andWhere('j.status IN (:statuses)')
            ->setParameter('deviceId', $deviceId)
            ->setParameter('statuses', FlashJob::ACTIVE_STATUSES)
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestForDevice(string $deviceId): ?FlashJob
    {
        return $this->em->createQueryBuilder()
            ->select('j')
            ->from(FlashJob::class, 'j')
            ->join('j.device', 'd')
            ->where('d.id = :deviceId')
            ->setParameter('deviceId', $deviceId)
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(FlashJob $job): void
    {
        $this->em->persist($job);
        $this->em->flush();
    }
}

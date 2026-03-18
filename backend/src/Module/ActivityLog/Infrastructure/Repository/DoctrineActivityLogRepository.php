<?php

declare(strict_types=1);

namespace App\Module\ActivityLog\Infrastructure\Repository;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function findRecent(
        ?Uuid $profileId = null,
        ?string $severity = null,
        int $limit = 50,
        int $offset = 0,
    ): array {
        $qb = $this->em->getRepository(ActivityLog::class)
            ->createQueryBuilder('a')
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($profileId !== null) {
            $qb->andWhere('a.familyProfileId = :profileId')
               ->setParameter('profileId', $profileId);
        }

        if ($severity !== null) {
            $qb->andWhere('a.severity = :severity')
               ->setParameter('severity', $severity);
        }

        return $qb->getQuery()->getResult();
    }

    public function countRecent(?Uuid $profileId = null, ?string $severity = null): int
    {
        $qb = $this->em->getRepository(ActivityLog::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)');

        if ($profileId !== null) {
            $qb->andWhere('a.familyProfileId = :profileId')
               ->setParameter('profileId', $profileId);
        }

        if ($severity !== null) {
            $qb->andWhere('a.severity = :severity')
               ->setParameter('severity', $severity);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function append(ActivityLog $entry): void
    {
        $this->em->persist($entry);
        $this->em->flush();
    }
}

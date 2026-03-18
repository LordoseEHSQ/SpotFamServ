<?php

declare(strict_types=1);

namespace App\Module\Scan\Infrastructure\Repository;

use App\Module\Scan\Application\Port\ScanEventRepositoryInterface;
use App\Module\Scan\Domain\ScanEvent;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineScanEventRepository implements ScanEventRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function append(
        string $cardUidRaw,
        string $outcome,
        ?string $readerId = null,
        ?string $profileId = null,
        ?array $details = null,
        ?string $readerDeviceId = null,
        ?string $rfidCardId = null,
        ?string $familyProfileId = null,
    ): void {
        $payload = $details ?? [];
        if ($readerId !== null) {
            $payload['reader_id'] = $readerId;
        }
        if ($profileId !== null) {
            $payload['profile_id'] = $profileId;
        }
        $event = new ScanEvent(
            $cardUidRaw,
            $outcome,
            $payload === [] ? null : $payload,
            $readerDeviceId,
            $rfidCardId,
            $familyProfileId,
        );
        $this->em->persist($event);
        $this->em->flush();
    }

    public function findRecentScan(string $cardUidRaw, int $withinSeconds, ?string $readerId = null): ?ScanEvent
    {
        $since = new \DateTimeImmutable('-' . $withinSeconds . ' seconds', new \DateTimeZone('UTC'));
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(ScanEvent::class, 'e')
            ->where('e.cardUidRaw = :uid')
            ->andWhere('e.createdAt > :since')
            ->setParameter('uid', $cardUidRaw)
            ->setParameter('since', $since)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(1);
        $result = $qb->getQuery()->getOneOrNullResult();
        return $result instanceof ScanEvent ? $result : null;
    }

    /** @return list<ScanEvent> */
    public function findRecent(int $limit = 50, int $offset = 0, ?string $profileId = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(ScanEvent::class, 'e')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);
        if ($profileId !== null) {
            $qb->andWhere('e.familyProfileId = :profileId')->setParameter('profileId', $profileId);
        }
        return $qb->getQuery()->getResult();
    }
}

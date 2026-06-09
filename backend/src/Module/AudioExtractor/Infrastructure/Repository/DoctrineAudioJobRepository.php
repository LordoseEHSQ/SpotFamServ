<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Infrastructure\Repository;

use App\Module\AudioExtractor\Application\Port\AudioJobRepositoryInterface;
use App\Module\AudioExtractor\Domain\AudioJob;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineAudioJobRepository implements AudioJobRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findById(string $id): ?AudioJob
    {
        // Guard against non-UUID input: find() would otherwise throw a conversion error (500)
        // on the uuid identifier type. A malformed id is simply "not found" (→ 404).
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->em->getRepository(AudioJob::class)->find($id);
    }

    public function recent(int $limit = 50): array
    {
        /** @var list<AudioJob> $jobs */
        $jobs = $this->em->getRepository(AudioJob::class)
            ->createQueryBuilder('j')
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $jobs;
    }

    public function save(AudioJob $job): void
    {
        $this->em->persist($job);
        $this->em->flush();
    }

    public function delete(AudioJob $job): void
    {
        $this->em->remove($job);
        $this->em->flush();
    }
}

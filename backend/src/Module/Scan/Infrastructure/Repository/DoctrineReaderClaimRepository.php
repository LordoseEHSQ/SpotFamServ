<?php

declare(strict_types=1);

namespace App\Module\Scan\Infrastructure\Repository;

use App\Module\Scan\Application\Port\ReaderClaimRepositoryInterface;
use App\Module\Scan\Domain\ReaderClaim;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineReaderClaimRepository implements ReaderClaimRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function findByCodeHash(string $claimCodeHash): ?ReaderClaim
    {
        return $this->em->getRepository(ReaderClaim::class)->findOneBy(['claimCodeHash' => $claimCodeHash]);
    }

    public function save(ReaderClaim $claim): void
    {
        $this->em->persist($claim);
        $this->em->flush();
    }
}

<?php

declare(strict_types=1);

namespace App\Module\FamilyProfile\Infrastructure\Repository;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\FamilyProfile\Domain\FamilyProfile;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineFamilyProfileRepository implements FamilyProfileRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function find(string $id): ?FamilyProfile
    {
        return $this->em->find(FamilyProfile::class, $id);
    }

    /** @return list<FamilyProfile> */
    public function findAll(): array
    {
        return $this->em->getRepository(FamilyProfile::class)->findBy([], ['name' => 'ASC']);
    }

    public function save(FamilyProfile $profile): void
    {
        $this->em->persist($profile);
        $this->em->flush();
    }

    public function remove(FamilyProfile $profile): void
    {
        $this->em->remove($profile);
        $this->em->flush();
    }
}

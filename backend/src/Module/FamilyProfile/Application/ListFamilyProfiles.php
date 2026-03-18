<?php

declare(strict_types=1);

namespace App\Module\FamilyProfile\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\FamilyProfile\Domain\FamilyProfile;

/**
 * Lists all family profiles (admin).
 */
final readonly class ListFamilyProfiles
{
    public function __construct(
        private FamilyProfileRepositoryInterface $repository,
    ) {
    }

    /** @return list<FamilyProfile> */
    public function __invoke(): array
    {
        return $this->repository->findAll();
    }
}

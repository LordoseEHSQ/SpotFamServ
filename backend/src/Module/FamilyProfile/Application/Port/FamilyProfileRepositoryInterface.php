<?php

declare(strict_types=1);

namespace App\Module\FamilyProfile\Application\Port;

use App\Module\FamilyProfile\Domain\FamilyProfile;

interface FamilyProfileRepositoryInterface
{
    public function find(string $id): ?FamilyProfile;

    /** @return list<FamilyProfile> */
    public function findAll(): array;

    public function save(FamilyProfile $profile): void;

    public function remove(FamilyProfile $profile): void;
}

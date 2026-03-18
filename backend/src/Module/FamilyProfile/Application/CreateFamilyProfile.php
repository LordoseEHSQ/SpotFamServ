<?php

declare(strict_types=1);

namespace App\Module\FamilyProfile\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\FamilyProfile\Domain\FamilyProfile;

final readonly class CreateFamilyProfile
{
    public function __construct(
        private FamilyProfileRepositoryInterface $repository,
    ) {
    }

    public function __invoke(string $name, ?string $description = null): FamilyProfile
    {
        $profile = new FamilyProfile($name, $description);
        $this->repository->save($profile);
        return $profile;
    }
}

<?php

declare(strict_types=1);

namespace App\Module\FamilyProfile\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\FamilyProfile\Domain\FamilyProfile;
use App\Shared\Application\Exception\NotFoundException;

final readonly class UpdateFamilyProfile
{
    public function __construct(
        private FamilyProfileRepositoryInterface $repository,
    ) {
    }

    public function __invoke(string $id, string $name, ?string $description = null): FamilyProfile
    {
        $profile = $this->repository->find($id);
        if ($profile === null) {
            throw new NotFoundException('Profile not found.');
        }
        $profile->setName($name);
        $profile->setDescription($description);
        $this->repository->save($profile);
        return $profile;
    }
}

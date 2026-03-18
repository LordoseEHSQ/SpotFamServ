<?php

declare(strict_types=1);

namespace App\Module\FamilyProfile\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Shared\Application\Exception\NotFoundException;

final readonly class DeleteFamilyProfile
{
    public function __construct(
        private FamilyProfileRepositoryInterface $repository,
    ) {
    }

    public function __invoke(string $id): void
    {
        $profile = $this->repository->find($id);
        if ($profile === null) {
            throw new NotFoundException('Profile not found.');
        }
        $this->repository->remove($profile);
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Rfid\Application;

use App\Module\Rfid\Application\Port\RfidCardRepositoryInterface;
use App\Module\Rfid\Domain\RfidCard;

final readonly class ListRfidCardsByProfile
{
    public function __construct(
        private RfidCardRepositoryInterface $repository,
    ) {
    }

    /** @return list<RfidCard> */
    public function __invoke(string $profileId): array
    {
        return $this->repository->findByProfileId($profileId);
    }
}

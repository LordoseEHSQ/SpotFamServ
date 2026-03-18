<?php

declare(strict_types=1);

namespace App\Module\Rfid\Application\Port;

use App\Module\Rfid\Domain\RfidCard;

interface RfidCardRepositoryInterface
{
    /** @return list<RfidCard> */
    public function findByProfileId(string $profileId): array;

    public function findById(string $id): ?RfidCard;

    public function findByCardUid(string $cardUid): ?RfidCard;

    public function save(RfidCard $card): void;

    public function remove(RfidCard $card): void;
}

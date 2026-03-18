<?php

declare(strict_types=1);

namespace App\Module\Rfid\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\Rfid\Application\Port\RfidCardRepositoryInterface;
use App\Module\Rfid\Domain\RfidCard;
use App\Shared\Application\Exception\NotFoundException;

final readonly class CreateRfidCard
{
    public function __construct(
        private RfidCardRepositoryInterface $cardRepository,
        private FamilyProfileRepositoryInterface $profileRepository,
    ) {
    }

    public function __invoke(string $profileId, string $cardUid, ?string $label = null): RfidCard
    {
        $profile = $this->profileRepository->find($profileId);
        if ($profile === null) {
            throw new NotFoundException('Profile not found.');
        }
        $cardUid = trim($cardUid);
        if ($cardUid === '') {
            throw new \InvalidArgumentException('card_uid is required.');
        }
        $existing = $this->cardRepository->findByCardUid($cardUid);
        if ($existing !== null) {
            throw new \DomainException('A card with this UID is already registered.');
        }
        $card = new RfidCard($profileId, $cardUid, $label);
        $this->cardRepository->save($card);
        return $card;
    }
}

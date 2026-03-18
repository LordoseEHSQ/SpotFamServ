<?php

declare(strict_types=1);

namespace App\Module\Rfid\Application;

use App\Module\Rfid\Application\Port\RfidCardRepositoryInterface;
use App\Module\Rfid\Domain\RfidCard;
use App\Shared\Application\Exception\NotFoundException;

final readonly class GetRfidCard
{
    public function __construct(
        private RfidCardRepositoryInterface $cardRepository,
    ) {
    }

    public function __invoke(string $profileId, string $cardId): RfidCard
    {
        $card = $this->cardRepository->findById($cardId);
        if ($card === null || $card->getFamilyProfileId() !== $profileId) {
            throw new NotFoundException('Card not found.');
        }
        return $card;
    }
}

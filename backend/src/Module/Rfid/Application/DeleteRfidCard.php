<?php

declare(strict_types=1);

namespace App\Module\Rfid\Application;

use App\Module\Rfid\Application\Port\CardPlaylistBindingRepositoryInterface;
use App\Module\Rfid\Application\Port\RfidCardRepositoryInterface;
use App\Shared\Application\Exception\NotFoundException;

final readonly class DeleteRfidCard
{
    public function __construct(
        private RfidCardRepositoryInterface $cardRepository,
        private CardPlaylistBindingRepositoryInterface $bindingRepository,
    ) {
    }

    public function __invoke(string $profileId, string $cardId): void
    {
        $card = $this->cardRepository->findById($cardId);
        if ($card === null || $card->getFamilyProfileId() !== $profileId) {
            throw new NotFoundException('Card not found.');
        }
        $binding = $this->bindingRepository->findByCardId($cardId);
        if ($binding !== null) {
            $this->bindingRepository->remove($binding);
        }
        $this->cardRepository->remove($card);
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Rfid\Application;

use App\Module\Rfid\Application\Port\CardPlaylistBindingRepositoryInterface;
use App\Module\Rfid\Application\Port\RfidCardRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyPlaylistReferenceRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyPlaylistReference;
use App\Shared\Application\Exception\NotFoundException;

final readonly class GetCardPlaylistBinding
{
    public function __construct(
        private CardPlaylistBindingRepositoryInterface $bindingRepository,
        private RfidCardRepositoryInterface $cardRepository,
        private SpotifyPlaylistReferenceRepositoryInterface $playlistRefRepository,
    ) {
    }

    public function __invoke(string $profileId, string $cardId): ?SpotifyPlaylistReference
    {
        $card = $this->cardRepository->findById($cardId);
        if ($card === null || $card->getFamilyProfileId() !== $profileId) {
            throw new NotFoundException('Card not found.');
        }
        $binding = $this->bindingRepository->findByCardId($cardId);
        if ($binding === null) {
            return null;
        }
        return $this->playlistRefRepository->findByIdAndProfile($binding->getSpotifyPlaylistReferenceId(), $profileId);
    }
}

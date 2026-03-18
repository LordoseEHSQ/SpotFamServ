<?php

declare(strict_types=1);

namespace App\Module\Rfid\Application;

use App\Module\Rfid\Application\Port\CardPlaylistBindingRepositoryInterface;
use App\Module\Rfid\Application\Port\RfidCardRepositoryInterface;
use App\Module\Rfid\Domain\CardPlaylistBinding;
use App\Module\Spotify\Application\Port\SpotifyPlaylistReferenceRepositoryInterface;
use App\Shared\Application\Exception\NotFoundException;

final readonly class SetCardPlaylistBinding
{
    public function __construct(
        private CardPlaylistBindingRepositoryInterface $bindingRepository,
        private RfidCardRepositoryInterface $cardRepository,
        private SpotifyPlaylistReferenceRepositoryInterface $playlistRefRepository,
    ) {
    }

    public function __invoke(string $profileId, string $cardId, ?string $spotifyPlaylistReferenceId): void
    {
        $card = $this->cardRepository->findById($cardId);
        if ($card === null || $card->getFamilyProfileId() !== $profileId) {
            throw new NotFoundException('Card not found.');
        }
        $existing = $this->bindingRepository->findByCardId($cardId);
        if ($spotifyPlaylistReferenceId === null || $spotifyPlaylistReferenceId === '') {
            if ($existing !== null) {
                $this->bindingRepository->remove($existing);
            }
            return;
        }
        $ref = $this->playlistRefRepository->findByIdAndProfile($spotifyPlaylistReferenceId, $profileId);
        if ($ref === null) {
            throw new NotFoundException('Playlist reference not found.');
        }
        if ($existing !== null) {
            $existing->setSpotifyPlaylistReferenceId($spotifyPlaylistReferenceId);
            $this->bindingRepository->save($existing);
        } else {
            $binding = new CardPlaylistBinding($cardId, $spotifyPlaylistReferenceId);
            $this->bindingRepository->save($binding);
        }
    }
}

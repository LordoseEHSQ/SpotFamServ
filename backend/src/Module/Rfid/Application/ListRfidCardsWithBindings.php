<?php

declare(strict_types=1);

namespace App\Module\Rfid\Application;

use App\Module\Rfid\Application\Port\CardPlaylistBindingRepositoryInterface;
use App\Module\Rfid\Application\Port\RfidCardRepositoryInterface;
use App\Module\Rfid\Domain\RfidCard;
use App\Module\Spotify\Application\Port\SpotifyPlaylistReferenceRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyPlaylistReference;

final readonly class ListRfidCardsWithBindings
{
    public function __construct(
        private RfidCardRepositoryInterface $cardRepository,
        private CardPlaylistBindingRepositoryInterface $bindingRepository,
        private SpotifyPlaylistReferenceRepositoryInterface $playlistRefRepository,
    ) {
    }

    /** @return list<array{card: RfidCard, binding: ?SpotifyPlaylistReference}> */
    public function __invoke(string $profileId): array
    {
        $cards = $this->cardRepository->findByProfileId($profileId);
        if ($cards === []) {
            return [];
        }

        $cardIds = array_values(array_filter(
            array_map(static fn(RfidCard $c) => $c->getId(), $cards),
        ));

        $bindings = $cardIds !== [] ? $this->bindingRepository->findByCardIds($cardIds) : [];

        $refIds = array_values(array_filter(
            array_map(static fn($b) => $b->getSpotifyPlaylistReferenceId(), $bindings),
        ));

        $refs = $refIds !== [] ? $this->playlistRefRepository->findByIds($refIds) : [];

        return array_map(function (RfidCard $card) use ($bindings, $refs): array {
            $cardId = $card->getId();
            $binding = $cardId !== null ? ($bindings[$cardId] ?? null) : null;
            $ref = $binding !== null ? ($refs[$binding->getSpotifyPlaylistReferenceId()] ?? null) : null;
            return ['card' => $card, 'binding' => $ref];
        }, $cards);
    }
}

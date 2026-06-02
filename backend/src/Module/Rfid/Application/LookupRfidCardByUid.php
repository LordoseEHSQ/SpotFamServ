<?php

declare(strict_types=1);

namespace App\Module\Rfid\Application;

use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Module\Rfid\Application\Port\CardPlaylistBindingRepositoryInterface;
use App\Module\Rfid\Application\Port\RfidCardRepositoryInterface;
use App\Module\Spotify\Application\Port\SpotifyPlaylistReferenceRepositoryInterface;

/**
 * Resolves a (raw) card UID to its assignment status. Matching is exact on the
 * trimmed UID — identical to how ProcessScan/CreateRfidCard handle it, so a UID
 * coming straight from a scan event matches a stored card. card_uid is globally
 * unique, hence a hit always belongs to exactly one profile.
 */
final readonly class LookupRfidCardByUid
{
    public function __construct(
        private RfidCardRepositoryInterface $cardRepository,
        private FamilyProfileRepositoryInterface $profileRepository,
        private CardPlaylistBindingRepositoryInterface $bindingRepository,
        private SpotifyPlaylistReferenceRepositoryInterface $playlistRefRepository,
    ) {
    }

    public function __invoke(string $cardUid): RfidCardLookupResult
    {
        $cardUid = trim($cardUid);
        $card = $cardUid === '' ? null : $this->cardRepository->findByCardUid($cardUid);
        if ($card === null) {
            return RfidCardLookupResult::free($cardUid);
        }

        $profileId = $card->getFamilyProfileId();
        $profile = $this->profileRepository->find($profileId);

        $bindingName = null;
        $cardId = $card->getId();
        if ($cardId !== null) {
            $binding = $this->bindingRepository->findByCardId($cardId);
            if ($binding !== null) {
                $ref = $this->playlistRefRepository->findByIdAndProfile(
                    $binding->getSpotifyPlaylistReferenceId(),
                    $profileId,
                );
                $bindingName = $ref?->getName();
            }
        }

        return RfidCardLookupResult::assigned(
            $card->getCardUid(),
            $profileId,
            $profile?->getName(),
            $card->getLabel(),
            $bindingName,
        );
    }
}

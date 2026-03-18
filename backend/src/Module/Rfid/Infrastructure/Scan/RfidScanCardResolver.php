<?php

declare(strict_types=1);

namespace App\Module\Rfid\Infrastructure\Scan;

use App\Module\Rfid\Application\Port\CardPlaylistBindingRepositoryInterface;
use App\Module\Rfid\Application\Port\RfidCardRepositoryInterface;
use App\Module\Scan\Application\Port\ScanCardResolverInterface;
use App\Module\Scan\Domain\ScanCardContext;
use App\Module\Spotify\Application\Port\SpotifyPlaylistReferenceRepositoryInterface;

/**
 * Implements ScanCardResolverInterface by querying Rfid and Spotify repositories.
 * Lives in Rfid/Infrastructure so the Scan module stays free of cross-module knowledge.
 */
final readonly class RfidScanCardResolver implements ScanCardResolverInterface
{
    public function __construct(
        private RfidCardRepositoryInterface $cardRepository,
        private CardPlaylistBindingRepositoryInterface $bindingRepository,
        private SpotifyPlaylistReferenceRepositoryInterface $playlistRefRepository,
    ) {
    }

    public function resolveCard(string $cardUid): ?ScanCardContext
    {
        $card = $this->cardRepository->findByCardUid($cardUid);
        if ($card === null) {
            return null;
        }
        $binding = $this->bindingRepository->findByCardId($card->getId());
        if ($binding === null) {
            return null;
        }
        $playlistRef = $this->playlistRefRepository->findById($binding->getSpotifyPlaylistReferenceId());
        if ($playlistRef === null) {
            return null;
        }
        return new ScanCardContext(
            $card->getId(),
            $card->getFamilyProfileId(),
            $playlistRef->getPlaylistUri(),
        );
    }
}

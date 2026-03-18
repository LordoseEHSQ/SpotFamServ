<?php

declare(strict_types=1);

namespace App\Module\Scan\Application\Port;

use App\Module\Scan\Domain\ScanCardContext;

/**
 * Resolves a card UID to its scan context (card id, profile id, playlist URI).
 * Abstracts away cross-module knowledge of Rfid and Spotify from the Scan module.
 */
interface ScanCardResolverInterface
{
    /**
     * Returns context or null if card is unknown or has no active playlist binding.
     */
    public function resolveCard(string $cardUid): ?ScanCardContext;
}

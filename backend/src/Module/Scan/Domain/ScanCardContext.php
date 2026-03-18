<?php

declare(strict_types=1);

namespace App\Module\Scan\Domain;

/**
 * Value Object: all data derived from a card UID scan that ProcessScan needs.
 */
final readonly class ScanCardContext
{
    public function __construct(
        public readonly string $cardId,
        public readonly string $profileId,
        public readonly string $playlistUri,
    ) {
    }
}

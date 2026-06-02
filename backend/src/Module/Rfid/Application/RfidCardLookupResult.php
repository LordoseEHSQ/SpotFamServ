<?php

declare(strict_types=1);

namespace App\Module\Rfid\Application;

/**
 * Read model for the "is this UID already in use?" lookup that backs the
 * Scan-to-Create UX. card_uid is globally unique, so a hit means the card
 * belongs to exactly one profile.
 */
final readonly class RfidCardLookupResult
{
    private function __construct(
        public string $status,
        public string $cardUid,
        public ?string $profileId,
        public ?string $profileName,
        public ?string $label,
        public bool $hasBinding,
        public ?string $bindingName,
    ) {
    }

    public static function free(string $cardUid): self
    {
        return new self('free', $cardUid, null, null, null, false, null);
    }

    public static function assigned(
        string $cardUid,
        string $profileId,
        ?string $profileName,
        ?string $label,
        ?string $bindingName,
    ): self {
        return new self('assigned', $cardUid, $profileId, $profileName, $label, $bindingName !== null, $bindingName);
    }
}

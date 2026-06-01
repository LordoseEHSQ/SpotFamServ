<?php

declare(strict_types=1);

namespace App\Module\Scan\Application\Port;

/**
 * Stores the "currently playing" session per reader so that physical
 * next/previous buttons know which profile (= Spotify account/token) to act on.
 *
 * MVP semantics: one Wobie Box ≈ one active session at a time. State is
 * ephemeral (cache with TTL); after expiry the user simply scans a card again.
 */
interface PlaybackSessionStoreInterface
{
    /**
     * Remember which profile last started playback (optionally per reader).
     */
    public function remember(string $profileId, string $readerId = ''): void;

    /**
     * Resolve the profile id for the current session. Prefers the reader-specific
     * session, falls back to the most recent global session. Null if none/expired.
     */
    public function currentProfileId(string $readerId = ''): ?string;
}

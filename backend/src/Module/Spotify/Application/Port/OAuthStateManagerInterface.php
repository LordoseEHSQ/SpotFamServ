<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Port;

/**
 * Stores and validates OAuth state to preserve profile context and prevent CSRF.
 */
interface OAuthStateManagerInterface
{
    /**
     * Generate a state value and store profileId for it (TTL e.g. 600s).
     */
    public function createState(string $profileId): string;

    /**
     * Validate state and return the profileId it was created for.
     *
     * @throws \App\Module\Spotify\Domain\Exception\SpotifyOAuthStateException when invalid or expired
     */
    public function consumeState(string $state): string;
}

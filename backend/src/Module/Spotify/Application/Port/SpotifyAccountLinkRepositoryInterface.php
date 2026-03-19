<?php

declare(strict_types=1);

namespace App\Module\Spotify\Application\Port;

use App\Module\Spotify\Domain\SpotifyAccountLink;

interface SpotifyAccountLinkRepositoryInterface
{
    public function findByProfileId(string $profileId): ?SpotifyAccountLink;

    public function save(SpotifyAccountLink $link): void;

    public function delete(SpotifyAccountLink $link): void;
}

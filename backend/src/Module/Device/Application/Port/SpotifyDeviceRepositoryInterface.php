<?php

declare(strict_types=1);

namespace App\Module\Device\Application\Port;

use App\Module\Device\Domain\SpotifyDevice;
use Symfony\Component\Uid\Uuid;

interface SpotifyDeviceRepositoryInterface
{
    public function findById(Uuid $id): ?SpotifyDevice;
    public function findBySpotifyDeviceId(string $spotifyDeviceId): ?SpotifyDevice;

    /** @return SpotifyDevice[] */
    public function findAll(): array;

    /** @return SpotifyDevice[] */
    public function findByProfileId(Uuid $profileId): array;

    public function save(SpotifyDevice $device): void;
    public function count(): int;
}

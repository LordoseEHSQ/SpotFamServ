<?php

declare(strict_types=1);

namespace App\Module\Rfid\Application\Port;

use App\Module\Rfid\Domain\CardPlaylistBinding;

interface CardPlaylistBindingRepositoryInterface
{
    public function findByCardId(string $rfidCardId): ?CardPlaylistBinding;

    public function save(CardPlaylistBinding $binding): void;

    public function remove(CardPlaylistBinding $binding): void;
}

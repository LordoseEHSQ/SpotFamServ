<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application\Port;

use App\Module\Provisioning\Domain\FlashJob;

interface FlashJobRepositoryInterface
{
    public function findById(string $id): ?FlashJob;

    /**
     * Gibt den ältesten pending-Job für das Gerät zurück (FIFO-Verarbeitung).
     * Setzt den Status NICHT auf running – das meldet der Agent separat.
     */
    public function findOldestPendingForDevice(string $deviceId): ?FlashJob;

    /**
     * Gibt einen aktiven Job (pending|running) für das Gerät zurück (für 409-Prüfung).
     */
    public function findActiveForDevice(string $deviceId): ?FlashJob;

    /**
     * Gibt den neuesten Job des Geräts zurück (für latestJob in der Geräteliste).
     */
    public function findLatestForDevice(string $deviceId): ?FlashJob;

    public function save(FlashJob $job): void;
}

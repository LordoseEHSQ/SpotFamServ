<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application\Port;

use App\Module\Provisioning\Domain\FlashArtifact;

interface FlashArtifactRepositoryInterface
{
    public function findById(string $id): ?FlashArtifact;

    /**
     * Sucht nach board+channel+version (für idempotentes Re-Registrieren via Console-Command).
     */
    public function findByBoardChannelVersion(string $board, string $channel, string $version): ?FlashArtifact;

    /** @return list<FlashArtifact> */
    public function findByBoardChannel(string $board, string $channel): array;

    /** @return list<FlashArtifact> */
    public function findAll(): array;

    public function save(FlashArtifact $artifact): void;
}

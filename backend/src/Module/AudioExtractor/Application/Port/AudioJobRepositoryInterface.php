<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Application\Port;

use App\Module\AudioExtractor\Domain\AudioJob;

interface AudioJobRepositoryInterface
{
    public function findById(string $id): ?AudioJob;

    /**
     * Most recent jobs first.
     *
     * @return list<AudioJob>
     */
    public function recent(int $limit = 50): array;

    public function save(AudioJob $job): void;

    public function delete(AudioJob $job): void;
}

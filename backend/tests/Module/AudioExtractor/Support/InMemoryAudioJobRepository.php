<?php

declare(strict_types=1);

namespace App\Tests\Module\AudioExtractor\Support;

use App\Module\AudioExtractor\Application\Port\AudioJobRepositoryInterface;
use App\Module\AudioExtractor\Domain\AudioJob;

/**
 * In-memory {@see AudioJobRepositoryInterface} for unit tests – no Doctrine/DB. The entity
 * self-assigns its UUID at construction, so save() only needs to remember it.
 */
final class InMemoryAudioJobRepository implements AudioJobRepositoryInterface
{
    /** @var array<string, AudioJob> */
    private array $jobs = [];

    public function findById(string $id): ?AudioJob
    {
        return $this->jobs[$id] ?? null;
    }

    public function recent(int $limit = 50): array
    {
        $jobs = array_values($this->jobs);
        usort($jobs, static fn (AudioJob $a, AudioJob $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        return array_slice($jobs, 0, $limit);
    }

    public function save(AudioJob $job): void
    {
        $this->jobs[$job->getId()] = $job;
    }
}

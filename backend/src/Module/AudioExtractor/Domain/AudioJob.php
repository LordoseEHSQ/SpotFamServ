<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A single asynchronous extraction request and its lifecycle (Sprint 07 / D-032).
 *
 * Lifecycle: pending → running → (done | failed); pending → canceled. The status is the
 * single user-facing source of truth – Messenger retries are disabled (D-033), so a failed
 * extraction is recorded here, not thrashed through the broker. `resultFile` holds the stored
 * file name once done; `error` holds a short, sanitised reason once failed.
 */
#[ORM\Entity]
#[ORM\Table(name: 'audio_job')]
#[ORM\Index(name: 'idx_audio_job_status', columns: ['status'])]
#[ORM\Index(name: 'idx_audio_job_created', columns: ['created_at'])]
class AudioJob
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_RUNNING  = 'running';
    public const STATUS_DONE     = 'done';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_CANCELED = 'canceled';

    // Self-assigned UUID (mirrors ActivityLog): the id exists at construction time, which keeps
    // the entity unit-testable without a flush and lets CreateAudioJob dispatch immediately.
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(name: 'url', type: 'text')]
    private string $url;

    #[ORM\Column(name: 'format', length: 8)]
    private string $format;

    #[ORM\Column(name: 'bitrate_kbps', nullable: true)]
    private ?int $bitrateKbps;

    #[ORM\Column(name: 'status', length: 16)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'progress')]
    private int $progress = 0;

    #[ORM\Column(name: 'error', type: 'text', nullable: true)]
    private ?string $error = null;

    #[ORM\Column(name: 'result_file', length: 255, nullable: true)]
    private ?string $resultFile = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $url, string $format, ?int $bitrateKbps)
    {
        $this->id = Uuid::v7();
        $this->url = $url;
        $this->format = $format;
        $this->bitrateKbps = $bitrateKbps;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): string
    {
        return (string) $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getBitrateKbps(): ?int
    {
        return $this->bitrateKbps;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getResultFile(): ?string
    {
        return $this->resultFile;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function markRunning(): void
    {
        $this->transitionTo(self::STATUS_RUNNING, [self::STATUS_PENDING]);
    }

    public function markDone(string $resultFile): void
    {
        $this->transitionTo(self::STATUS_DONE, [self::STATUS_RUNNING]);
        $this->resultFile = $resultFile;
        $this->progress = 100;
    }

    public function markFailed(string $error): void
    {
        // A job may fail from pending (e.g. validation) or running (extraction error).
        $this->transitionTo(self::STATUS_FAILED, [self::STATUS_PENDING, self::STATUS_RUNNING]);
        $this->error = $this->truncate($error);
    }

    /**
     * Best-effort cancel: only a job that has not started yet can be safely canceled
     * (a running yt-dlp subprocess cannot be reliably interrupted here).
     */
    public function cancel(): void
    {
        $this->transitionTo(self::STATUS_CANCELED, [self::STATUS_PENDING]);
    }

    public function setProgress(int $progress): void
    {
        $this->progress = max(0, min(100, $progress));
        $this->touch();
    }

    /**
     * @param list<string> $allowedFrom
     */
    private function transitionTo(string $newStatus, array $allowedFrom): void
    {
        if (!in_array($this->status, $allowedFrom, true)) {
            throw new \DomainException(sprintf(
                'Illegal AudioJob transition %s → %s.',
                $this->status,
                $newStatus,
            ));
        }
        $this->status = $newStatus;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function truncate(string $text): string
    {
        $text = trim($text);

        return mb_strlen($text) > 2000 ? mb_substr($text, 0, 2000) . '…' : $text;
    }
}

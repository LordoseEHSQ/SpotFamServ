<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Repräsentiert einen Flash-Auftrag für ein erkanntes Gerät.
 * Invariante: Pro Gerät darf es nur einen aktiven Job (status pending|running) geben.
 * Diese Invariante wird in CreateJob per TransactionRunner atomar geprüft.
 */
#[ORM\Entity]
#[ORM\Table(name: 'provisioning_flash_job')]
class FlashJob
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    /** Alle Status, bei denen kein weiterer Job angelegt werden darf. */
    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_RUNNING];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: DetectedDevice::class)]
    #[ORM\JoinColumn(name: 'device_id', referencedColumnName: 'id', nullable: false)]
    private DetectedDevice $device;

    #[ORM\ManyToOne(targetEntity: FlashArtifact::class)]
    #[ORM\JoinColumn(name: 'artifact_id', referencedColumnName: 'id', nullable: false)]
    private FlashArtifact $artifact;

    #[ORM\Column(name: 'status', length: 16)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'progress')]
    private int $progress = 0;

    #[ORM\Column(name: 'message', type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(DetectedDevice $device, FlashArtifact $artifact)
    {
        $this->device    = $device;
        $this->artifact  = $artifact;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDevice(): DetectedDevice
    {
        return $this->device;
    }

    public function getArtifact(): FlashArtifact
    {
        return $this->artifact;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    /**
     * Setzt Status, optionalen Fortschritt und optionale Meldung.
     * Erlaubte Übergänge: pending→running, running→success, running→failed.
     * Ungültige Übergänge werden mit einer DomainException abgelehnt.
     */
    public function applyStatusUpdate(string $newStatus, ?int $progress, ?string $message): void
    {
        $allowed = match ($this->status) {
            self::STATUS_PENDING => [self::STATUS_RUNNING],
            self::STATUS_RUNNING => [self::STATUS_SUCCESS, self::STATUS_FAILED],
            default              => [],
        };

        if (!in_array($newStatus, $allowed, true)) {
            throw new \DomainException(sprintf(
                'Statusübergang %s→%s ist nicht erlaubt.',
                $this->status,
                $newStatus,
            ));
        }

        $this->status    = $newStatus;
        $this->progress  = $progress ?? $this->progress;
        $this->message   = $message ?? $this->message;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

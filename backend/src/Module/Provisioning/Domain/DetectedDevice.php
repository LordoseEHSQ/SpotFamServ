<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Repräsentiert ein physisch erkanntes ESP32-Gerät, das an einem Flash-Port gefunden wurde.
 * Wird per MAC-Adresse eindeutig identifiziert; lastSeenAt wird bei jedem Detect-Poll aktualisiert.
 */
#[ORM\Entity]
#[ORM\Table(name: 'provisioning_detected_device')]
#[ORM\UniqueConstraint(name: 'uniq_provisioning_device_mac', columns: ['mac'])]
class DetectedDevice
{
    public const STATUS_IDLE    = 'idle';
    public const STATUS_FLASHING = 'flashing';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'port', length: 64)]
    private string $port;

    #[ORM\Column(name: 'chip', length: 64)]
    private string $chip;

    #[ORM\Column(name: 'chip_description', length: 255)]
    private string $chipDescription;

    #[ORM\Column(name: 'mac', length: 32)]
    private string $mac;

    #[ORM\Column(name: 'flash_size', length: 32)]
    private string $flashSize;

    #[ORM\Column(name: 'first_seen_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $firstSeenAt;

    #[ORM\Column(name: 'last_seen_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column(name: 'status', length: 16)]
    private string $status = self::STATUS_IDLE;

    public function __construct(
        string $port,
        string $chip,
        string $chipDescription,
        string $mac,
        string $flashSize,
    ) {
        $this->port            = $port;
        $this->chip            = $chip;
        $this->chipDescription = $chipDescription;
        $this->mac             = $mac;
        $this->flashSize       = $flashSize;
        $this->firstSeenAt     = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->lastSeenAt      = $this->firstSeenAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPort(): string
    {
        return $this->port;
    }

    public function getChip(): string
    {
        return $this->chip;
    }

    public function getChipDescription(): string
    {
        return $this->chipDescription;
    }

    public function getMac(): string
    {
        return $this->mac;
    }

    public function getFlashSize(): string
    {
        return $this->flashSize;
    }

    public function getFirstSeenAt(): \DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Aktualisiert veränderliche Geräteeigenschaften beim nächsten Detect-Poll.
     * firstSeenAt bleibt unverändert.
     */
    public function updateDetection(string $port, string $chip, string $chipDescription, string $flashSize): void
    {
        $this->port            = $port;
        $this->chip            = $chip;
        $this->chipDescription = $chipDescription;
        $this->flashSize       = $flashSize;
        $this->lastSeenAt      = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function markIdle(): void
    {
        $this->status = self::STATUS_IDLE;
    }
}

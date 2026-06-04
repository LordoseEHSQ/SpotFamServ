<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Repräsentiert ein registriertes Firmware-Artefakt, das per Console-Command registriert wurde.
 * Kein freier Upload – nur der registrierte Dateiname (relativ zu FIRMWARE_DIR) ist zulässig.
 * CI trägt Artefakte später über `app:provisioning:register-artifact` ein.
 */
#[ORM\Entity]
#[ORM\Table(name: 'provisioning_flash_artifact')]
class FlashArtifact
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'board', length: 64)]
    private string $board;

    #[ORM\Column(name: 'channel', length: 32)]
    private string $channel;

    #[ORM\Column(name: 'version', length: 64)]
    private string $version;

    /** Relativer Dateiname innerhalb von FIRMWARE_DIR (kein / oder ..). */
    #[ORM\Column(name: 'filename', length: 255)]
    private string $filename;

    #[ORM\Column(name: 'sha256', length: 64)]
    private string $sha256;

    #[ORM\Column(name: 'expected_chip', length: 64)]
    private string $expectedChip;

    #[ORM\Column(name: 'size_bytes')]
    private int $sizeBytes;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $board,
        string $channel,
        string $version,
        string $filename,
        string $sha256,
        string $expectedChip,
        int $sizeBytes,
    ) {
        $this->board        = $board;
        $this->channel      = $channel;
        $this->version      = $version;
        $this->filename     = $filename;
        $this->sha256       = $sha256;
        $this->expectedChip = $expectedChip;
        $this->sizeBytes    = $sizeBytes;
        $this->createdAt    = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getBoard(): string
    {
        return $this->board;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function getExpectedChip(): string
    {
        return $this->expectedChip;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Aktualisiert sha256, sizeBytes und filename bei Re-Registrierung desselben Artefakts.
     * board/channel/version/expectedChip bleiben die Identität.
     */
    public function updateContent(string $filename, string $sha256, int $sizeBytes): void
    {
        $this->filename  = $filename;
        $this->sha256    = $sha256;
        $this->sizeBytes = $sizeBytes;
    }
}

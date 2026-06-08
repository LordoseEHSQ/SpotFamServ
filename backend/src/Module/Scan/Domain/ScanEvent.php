<?php

declare(strict_types=1);

namespace App\Module\Scan\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'scan_event')]
class ScanEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'card_uid_raw', length: 64)]
    private string $cardUidRaw;

    #[ORM\Column(name: 'outcome', length: 64)]
    private string $outcome;

    #[ORM\Column(name: 'reader_device_id', type: 'uuid', nullable: true)]
    private ?string $readerDeviceId = null;

    #[ORM\Column(name: 'rfid_card_id', type: 'uuid', nullable: true)]
    private ?string $rfidCardId = null;

    #[ORM\Column(name: 'family_profile_id', type: 'uuid', nullable: true)]
    private ?string $familyProfileId = null;

    #[ORM\Column(name: 'details', type: 'json', nullable: true)]
    private ?array $details = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $cardUidRaw,
        string $outcome,
        ?array $details = null,
        ?string $readerDeviceId = null,
        ?string $rfidCardId = null,
        ?string $familyProfileId = null,
    ) {
        $this->cardUidRaw = $cardUidRaw;
        $this->outcome = $outcome;
        $this->details = $details;
        $this->readerDeviceId = $readerDeviceId;
        $this->rfidCardId = $rfidCardId;
        $this->familyProfileId = $familyProfileId;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCardUidRaw(): string
    {
        return $this->cardUidRaw;
    }

    public function getOutcome(): string
    {
        return $this->outcome;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReaderDeviceId(): ?string
    {
        return $this->readerDeviceId;
    }

    public function getRfidCardId(): ?string
    {
        return $this->rfidCardId;
    }

    public function getFamilyProfileId(): ?string
    {
        return $this->familyProfileId;
    }

    /** @return array<string, mixed>|null */
    public function getDetails(): ?array
    {
        return $this->details;
    }
}

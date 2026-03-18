<?php

declare(strict_types=1);

namespace App\Module\Rfid\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rfid_card')]
#[ORM\UniqueConstraint(name: 'uniq_card_uid', columns: ['card_uid'])]
class RfidCard
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'family_profile_id', type: 'uuid')]
    private string $familyProfileId;

    #[ORM\Column(name: 'card_uid', length: 64)]
    private string $cardUid;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $familyProfileId, string $cardUid, ?string $label = null)
    {
        $this->familyProfileId = $familyProfileId;
        $this->cardUid = $cardUid;
        $this->label = $label;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getFamilyProfileId(): string
    {
        return $this->familyProfileId;
    }

    public function getCardUid(): string
    {
        return $this->cardUid;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

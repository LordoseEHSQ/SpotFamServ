<?php

declare(strict_types=1);

namespace App\Module\Rfid\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'card_playlist_binding')]
#[ORM\UniqueConstraint(name: 'uniq_rfid_card', columns: ['rfid_card_id'])]
class CardPlaylistBinding
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'rfid_card_id', type: 'uuid')]
    private string $rfidCardId;

    #[ORM\Column(name: 'spotify_playlist_reference_id', type: 'uuid')]
    private string $spotifyPlaylistReferenceId;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $rfidCardId, string $spotifyPlaylistReferenceId)
    {
        $this->rfidCardId = $rfidCardId;
        $this->spotifyPlaylistReferenceId = $spotifyPlaylistReferenceId;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getRfidCardId(): string
    {
        return $this->rfidCardId;
    }

    public function getSpotifyPlaylistReferenceId(): string
    {
        return $this->spotifyPlaylistReferenceId;
    }

    public function setSpotifyPlaylistReferenceId(string $id): void
    {
        $this->spotifyPlaylistReferenceId = $id;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

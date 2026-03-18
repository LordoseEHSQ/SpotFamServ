<?php

declare(strict_types=1);

namespace App\Module\Spotify\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'spotify_playlist_reference')]
#[ORM\UniqueConstraint(name: 'uniq_profile_playlist', columns: ['family_profile_id', 'spotify_playlist_id'])]
class SpotifyPlaylistReference
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'family_profile_id', type: 'uuid')]
    private string $familyProfileId;

    #[ORM\Column(name: 'spotify_playlist_id', length: 255)]
    private string $spotifyPlaylistId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(name: 'owner_id', length: 255, nullable: true)]
    private ?string $ownerId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $familyProfileId, string $spotifyPlaylistId, string $name, ?string $ownerId = null)
    {
        $this->familyProfileId = $familyProfileId;
        $this->spotifyPlaylistId = $spotifyPlaylistId;
        $this->name = $name;
        $this->ownerId = $ownerId;
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

    public function getSpotifyPlaylistId(): string
    {
        return $this->spotifyPlaylistId;
    }

    public function getPlaylistUri(): string
    {
        return 'spotify:playlist:' . $this->spotifyPlaylistId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }
}

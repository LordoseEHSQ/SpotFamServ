<?php

declare(strict_types=1);

namespace App\Module\FamilyProfile\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'family_profile')]
class FamilyProfile
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'default_spotify_device_id', length: 255, nullable: true)]
    private ?string $defaultSpotifyDeviceId = null;

    #[ORM\Column(name: 'default_device_name', length: 255, nullable: true)]
    private ?string $defaultDeviceName = null;

    #[ORM\Column(name: 'status', length: 32, options: ['default' => 'active'])]
    private string $status = 'active';

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, ?string $description = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function getDefaultSpotifyDeviceId(): ?string
    {
        return $this->defaultSpotifyDeviceId;
    }

    public function setDefaultSpotifyDeviceId(?string $defaultSpotifyDeviceId): void
    {
        $this->defaultSpotifyDeviceId = $defaultSpotifyDeviceId;
        $this->touch();
    }

    public function getDefaultDeviceName(): ?string
    {
        return $this->defaultDeviceName;
    }

    /**
     * Sets the default playback device. Stores the human-readable name alongside the
     * (ephemeral) Spotify device id so the name can be displayed and the id can be
     * re-resolved by name if Spotify hands out a new id for the same device.
     */
    public function setDefaultDevice(?string $deviceId, ?string $deviceName): void
    {
        $this->defaultSpotifyDeviceId = ($deviceId === null || $deviceId === '') ? null : $deviceId;
        $this->defaultDeviceName = ($deviceName === null || $deviceName === '') ? null : $deviceName;
        $this->touch();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Scan\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reader_device')]
#[ORM\UniqueConstraint(name: 'uniq_reader_id', columns: ['reader_id'])]
class ReaderDevice
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'reader_id', length: 64)]
    private string $readerId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'api_key_hash', length: 255, nullable: true)]
    private ?string $apiKeyHash = null;

    #[ORM\Column(name: 'default_spotify_device_id', length: 255, nullable: true)]
    private ?string $defaultSpotifyDeviceId = null;

    #[ORM\Column(name: 'default_device_name', length: 255, nullable: true)]
    private ?string $defaultDeviceName = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $readerId, ?string $name = null, ?string $apiKeyHash = null)
    {
        $this->readerId = $readerId;
        $this->name = $name;
        $this->apiKeyHash = $apiKeyHash;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getReaderId(): string
    {
        return $this->readerId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getApiKeyHash(): ?string
    {
        return $this->apiKeyHash;
    }

    public function validateApiKey(string $apiKey): bool
    {
        if ($this->apiKeyHash === null) {
            return false;
        }
        return password_verify($apiKey, $this->apiKeyHash);
    }

    public function hasApiKey(): bool
    {
        return $this->apiKeyHash !== null;
    }

    /**
     * Sets (or rotates) the reader's dedicated API key by storing only its hash.
     * The plain key is never persisted; the caller hands it to the operator once.
     */
    public function setApiKey(string $plainKey): void
    {
        $this->apiKeyHash = password_hash($plainKey, PASSWORD_DEFAULT);
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Removes the reader's dedicated key so authentication falls back to the
     * global READER_API_KEY again (used to revoke a compromised key).
     */
    public function clearApiKey(): void
    {
        $this->apiKeyHash = null;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getDefaultSpotifyDeviceId(): ?string
    {
        return $this->defaultSpotifyDeviceId;
    }

    public function getDefaultDeviceName(): ?string
    {
        return $this->defaultDeviceName;
    }

    /**
     * Sets the reader's default playback device (room box). Stores the human-readable
     * name alongside the (ephemeral) Spotify device id so it can be displayed and
     * re-resolved by name if Spotify hands out a new id for the same device (D-015).
     */
    public function setDefaultDevice(?string $deviceId, ?string $deviceName): void
    {
        $this->defaultSpotifyDeviceId = ($deviceId === null || $deviceId === '') ? null : $deviceId;
        $this->defaultDeviceName = ($deviceName === null || $deviceName === '') ? null : $deviceName;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

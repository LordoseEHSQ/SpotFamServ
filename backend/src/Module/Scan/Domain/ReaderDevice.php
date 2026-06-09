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

    #[ORM\Column(name: 'last_seen_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(name: 'firmware_version', length: 20, nullable: true)]
    private ?string $firmwareVersion = null;

    #[ORM\Column(name: 'board', length: 64, nullable: true)]
    private ?string $board = null;

    #[ORM\Column(name: 'fw_channel', length: 32, nullable: true)]
    private ?string $fwChannel = null;

    #[ORM\Column(name: 'last_ip', length: 45, nullable: true)]
    private ?string $lastIp = null;

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

    public function setApiKey(string $plainKey): void
    {
        $this->apiKeyHash = password_hash($plainKey, PASSWORD_DEFAULT);
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

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

    public function setDefaultDevice(?string $deviceId, ?string $deviceName): void
    {
        $this->defaultSpotifyDeviceId = ($deviceId === null || $deviceId === '') ? null : $deviceId;
        $this->defaultDeviceName = ($deviceName === null || $deviceName === '') ? null : $deviceName;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function getFirmwareVersion(): ?string
    {
        return $this->firmwareVersion;
    }

    public function getBoard(): ?string
    {
        return $this->board;
    }

    public function getFwChannel(): ?string
    {
        return $this->fwChannel;
    }

    public function getLastIp(): ?string
    {
        return $this->lastIp;
    }

    /**
     * Records reader activity: updates last_seen_at and optionally persists firmware metadata.
     * Null values leave existing data untouched.
     */
    public function touchSeen(
        ?string $ip = null,
        ?string $firmwareVersion = null,
        ?string $board = null,
        ?string $fwChannel = null,
    ): void {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->lastSeenAt = $now;
        $this->updatedAt = $now;
        if ($ip !== null && $ip !== '') {
            $this->lastIp = $ip;
        }
        if ($firmwareVersion !== null && $firmwareVersion !== '') {
            $this->firmwareVersion = $firmwareVersion;
        }
        if ($board !== null && $board !== '') {
            $this->board = $board;
        }
        if ($fwChannel !== null && $fwChannel !== '') {
            $this->fwChannel = $fwChannel;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Device\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'spotify_device')]
#[ORM\UniqueConstraint(name: 'uniq_spotify_device_id', columns: ['spotify_device_id'])]
#[ORM\Index(name: 'idx_spotify_device_profile', columns: ['assigned_family_profile_id'])]
#[ORM\Index(name: 'idx_spotify_device_available', columns: ['is_available', 'assignment_mode'])]
class SpotifyDevice
{
    public const ASSIGNMENT_UNASSIGNED = 'unassigned';
    public const ASSIGNMENT_ASSIGNED   = 'assigned';
    public const ASSIGNMENT_RESERVED   = 'reserved';
    public const ASSIGNMENT_LOCKED     = 'locked';
    public const ASSIGNMENT_SHARED     = 'shared';

    public const DISCOVERY_AVAILABLE   = 'available';
    public const DISCOVERY_UNAVAILABLE = 'unavailable';
    public const DISCOVERY_CONFLICT    = 'conflict';
    public const DISCOVERY_UNKNOWN     = 'unknown';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(name: 'spotify_device_id', type: 'string', length: 255)]
    private string $spotifyDeviceId;

    #[ORM\Column(name: 'spotify_device_name', type: 'string', length: 255)]
    private string $spotifyDeviceName;

    #[ORM\Column(name: 'device_type', type: 'string', length: 64, nullable: true)]
    private ?string $deviceType = null;

    #[ORM\Column(name: 'is_available', type: 'boolean')]
    private bool $isAvailable = false;

    #[ORM\Column(name: 'last_seen_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(name: 'assigned_family_profile_id', type: 'uuid', nullable: true)]
    private ?Uuid $assignedFamilyProfileId = null;

    #[ORM\Column(name: 'assignment_mode', type: 'string', length: 32)]
    private string $assignmentMode = self::ASSIGNMENT_UNASSIGNED;

    #[ORM\Column(name: 'assignment_updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $assignmentUpdatedAt = null;

    #[ORM\Column(name: 'assignment_note', type: 'text', nullable: true)]
    private ?string $assignmentNote = null;

    #[ORM\Column(name: 'discovery_status', type: 'string', length: 32)]
    private string $discoveryStatus = self::DISCOVERY_UNKNOWN;

    #[ORM\Column(name: 'last_discovery_run_id', type: 'uuid', nullable: true)]
    private ?Uuid $lastDiscoveryRunId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $spotifyDeviceId, string $spotifyDeviceName, ?string $deviceType = null)
    {
        $this->id                = Uuid::v7();
        $this->spotifyDeviceId   = $spotifyDeviceId;
        $this->spotifyDeviceName = $spotifyDeviceName;
        $this->deviceType        = $deviceType;
        $this->createdAt         = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt         = $this->createdAt;
    }

    public function getId(): Uuid { return $this->id; }
    public function getSpotifyDeviceId(): string { return $this->spotifyDeviceId; }
    public function getSpotifyDeviceName(): string { return $this->spotifyDeviceName; }
    public function getDeviceType(): ?string { return $this->deviceType; }
    public function isAvailable(): bool { return $this->isAvailable; }
    public function getLastSeenAt(): ?\DateTimeImmutable { return $this->lastSeenAt; }
    public function getAssignedFamilyProfileId(): ?Uuid { return $this->assignedFamilyProfileId; }
    public function getAssignmentMode(): string { return $this->assignmentMode; }
    public function getAssignmentUpdatedAt(): ?\DateTimeImmutable { return $this->assignmentUpdatedAt; }
    public function getAssignmentNote(): ?string { return $this->assignmentNote; }
    public function getDiscoveryStatus(): string { return $this->discoveryStatus; }
    public function getLastDiscoveryRunId(): ?Uuid { return $this->lastDiscoveryRunId; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function markSeen(bool $available, ?Uuid $discoveryRunId = null): void
    {
        $this->isAvailable       = $available;
        $this->lastSeenAt        = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->discoveryStatus   = $available ? self::DISCOVERY_AVAILABLE : self::DISCOVERY_UNAVAILABLE;
        $this->lastDiscoveryRunId = $discoveryRunId;
        $this->touch();
    }

    public function assign(
        ?Uuid $profileId,
        string $mode = self::ASSIGNMENT_ASSIGNED,
        ?string $note = null,
    ): void {
        $this->assignedFamilyProfileId = $profileId;
        $this->assignmentMode          = $profileId === null ? self::ASSIGNMENT_UNASSIGNED : $mode;
        $this->assignmentNote          = $note;
        $this->assignmentUpdatedAt     = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->touch();
    }

    public function isAssignedTo(Uuid $profileId): bool
    {
        return $this->assignedFamilyProfileId !== null
            && $this->assignedFamilyProfileId->equals($profileId);
    }

    public function hasConflict(Uuid $requestingProfileId): bool
    {
        return $this->assignedFamilyProfileId !== null
            && $this->assignmentMode === self::ASSIGNMENT_ASSIGNED
            && !$this->assignedFamilyProfileId->equals($requestingProfileId);
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

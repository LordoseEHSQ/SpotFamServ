<?php

declare(strict_types=1);

namespace App\Module\ActivityLog\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'activity_log')]
#[ORM\Index(name: 'idx_activity_log_profile', columns: ['family_profile_id'])]
#[ORM\Index(name: 'idx_activity_log_occurred', columns: ['occurred_at'])]
#[ORM\Index(name: 'idx_activity_log_severity', columns: ['severity', 'occurred_at'])]
class ActivityLog
{
    public const SEVERITY_DEBUG    = 'debug';
    public const SEVERITY_INFO     = 'info';
    public const SEVERITY_WARNING  = 'warning';
    public const SEVERITY_ERROR    = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    public const TYPE_RFID_SCAN             = 'rfid_scan';
    public const TYPE_PLAYBACK_STARTED      = 'playback_started';
    public const TYPE_PLAYBACK_BLOCKED      = 'playback_blocked';
    public const TYPE_PLAYBACK_FAILED       = 'playback_failed';
    public const TYPE_SPOTIFY_CONNECTED     = 'spotify_connected';
    public const TYPE_SPOTIFY_DISCONNECTED  = 'spotify_disconnected';
    public const TYPE_SPOTIFY_VALIDATED     = 'spotify_validated';
    public const TYPE_SPOTIFY_TOKEN_REFRESH = 'spotify_token_refreshed';
    public const TYPE_SPOTIFY_REAUTH_REQUIRED = 'spotify_reauth_required';
    public const TYPE_DEVICE_ASSIGNED       = 'device_assigned';
    public const TYPE_DEVICE_UNASSIGNED     = 'device_unassigned';
    public const TYPE_DEVICE_DISCOVERED     = 'device_discovered';
    public const TYPE_DEVICE_CONFLICT       = 'device_conflict';
    public const TYPE_DEVICE_NOT_AVAILABLE  = 'device_not_available';
    public const TYPE_RULE_LIMIT_REACHED    = 'rule_limit_reached';
    public const TYPE_SETUP_COMPLETED       = 'setup_completed';
    public const TYPE_SYSTEM                = 'system';
    public const TYPE_PLAYLIST_CREATED      = 'playlist_created';
    public const TYPE_PLAYLIST_CHANGED      = 'playlist_changed';
    public const TYPE_PLAYBACK_PAUSED       = 'playback_paused';
    public const TYPE_PLAYBACK_NEXT         = 'playback_next';
    public const TYPE_PLAYBACK_PREVIOUS     = 'playback_previous';
    public const TYPE_SEARCH_EXECUTED       = 'search_executed';
    public const TYPE_READER_CLAIM_CREATED  = 'reader_claim_created';
    public const TYPE_READER_CLAIM_REDEEMED = 'reader_claim_redeemed';
    public const TYPE_READER_CLAIM_FAILED   = 'reader_claim_failed';

    // Provisioning-Modul (Flash-Station)
    public const TYPE_PROVISIONING_DEVICE_DETECTED = 'provisioning_device_detected';
    public const TYPE_FLASH_JOB_CREATED            = 'flash_job_created';
    public const TYPE_FLASH_JOB_STARTED            = 'flash_job_started';
    public const TYPE_FLASH_JOB_SUCCEEDED          = 'flash_job_succeeded';
    public const TYPE_FLASH_JOB_FAILED             = 'flash_job_failed';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(name: 'family_profile_id', type: 'uuid', nullable: true)]
    private ?Uuid $familyProfileId;

    #[ORM\Column(name: 'related_entity_type', type: 'string', length: 64, nullable: true)]
    private ?string $relatedEntityType;

    #[ORM\Column(name: 'related_entity_id', type: 'string', length: 255, nullable: true)]
    private ?string $relatedEntityId;

    #[ORM\Column(name: 'activity_type', type: 'string', length: 64)]
    private string $activityType;

    #[ORM\Column(name: 'severity', type: 'string', length: 16)]
    private string $severity;

    #[ORM\Column(name: 'message', type: 'string', length: 512)]
    private string $message;

    #[ORM\Column(name: 'details', type: 'json', nullable: true)]
    private ?array $details;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        string $activityType,
        string $message,
        string $severity = self::SEVERITY_INFO,
        ?Uuid $familyProfileId = null,
        ?string $relatedEntityType = null,
        ?string $relatedEntityId = null,
        ?array $details = null,
    ) {
        $this->id                = Uuid::v7();
        $this->activityType      = $activityType;
        $this->message           = $message;
        $this->severity          = $severity;
        $this->familyProfileId   = $familyProfileId;
        $this->relatedEntityType = $relatedEntityType;
        $this->relatedEntityId   = $relatedEntityId;
        $this->details           = $details;
        $this->occurredAt        = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): Uuid { return $this->id; }
    public function getFamilyProfileId(): ?Uuid { return $this->familyProfileId; }
    public function getRelatedEntityType(): ?string { return $this->relatedEntityType; }
    public function getRelatedEntityId(): ?string { return $this->relatedEntityId; }
    public function getActivityType(): string { return $this->activityType; }
    public function getSeverity(): string { return $this->severity; }
    public function getMessage(): string { return $this->message; }
    public function getDetails(): ?array { return $this->details; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}

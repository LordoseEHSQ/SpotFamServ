<?php

declare(strict_types=1);

namespace App\Module\Device\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'device_discovery_run')]
#[ORM\Index(name: 'idx_discovery_run_started', columns: ['started_at'])]
class DeviceDiscoveryRun
{
    public const STATUS_RUNNING    = 'running';
    public const STATUS_SUCCESS    = 'success';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_NO_DEVICES = 'no_devices';
    public const STATUS_PARTIAL    = 'partial';

    public const SCOPE_GLOBAL  = 'global';
    public const SCOPE_PROFILE = 'profile';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(name: 'scope', type: 'string', length: 64)]
    private string $scope;

    #[ORM\Column(name: 'scope_profile_id', type: 'uuid', nullable: true)]
    private ?Uuid $scopeProfileId = null;

    #[ORM\Column(name: 'result_status', type: 'string', length: 32, nullable: true)]
    private ?string $resultStatus = null;

    #[ORM\Column(name: 'devices_found_count', type: 'integer')]
    private int $devicesFoundCount = 0;

    #[ORM\Column(name: 'devices_available_count', type: 'integer')]
    private int $devicesAvailableCount = 0;

    #[ORM\Column(name: 'devices_new_count', type: 'integer')]
    private int $devicesNewCount = 0;

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'raw_payload', type: 'json', nullable: true)]
    private ?array $rawPayload = null;

    public function __construct(string $scope = self::SCOPE_GLOBAL, ?Uuid $scopeProfileId = null)
    {
        $this->id             = Uuid::v7();
        $this->scope          = $scope;
        $this->scopeProfileId = $scopeProfileId;
        $this->startedAt      = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->resultStatus   = self::STATUS_RUNNING;
    }

    public function getId(): Uuid { return $this->id; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getFinishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function getScope(): string { return $this->scope; }
    public function getScopeProfileId(): ?Uuid { return $this->scopeProfileId; }
    public function getResultStatus(): ?string { return $this->resultStatus; }
    public function getDevicesFoundCount(): int { return $this->devicesFoundCount; }
    public function getDevicesAvailableCount(): int { return $this->devicesAvailableCount; }
    public function getDevicesNewCount(): int { return $this->devicesNewCount; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function getRawPayload(): ?array { return $this->rawPayload; }

    public function finish(
        string $status,
        int $found,
        int $available,
        int $newCount,
        ?string $errorMessage = null,
        ?array $rawPayload = null,
    ): void {
        $this->finishedAt           = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->resultStatus         = $status;
        $this->devicesFoundCount    = $found;
        $this->devicesAvailableCount = $available;
        $this->devicesNewCount      = $newCount;
        $this->errorMessage         = $errorMessage;
        $this->rawPayload           = $rawPayload;
    }
}

<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'profile_setup_step_status')]
#[ORM\UniqueConstraint(name: 'uniq_session_step', columns: ['profile_setup_session_id', 'step_key'])]
class ProfileSetupStepStatus
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REQUIRES_ATTENTION = 'requires_attention';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'profile_setup_session_id', type: 'uuid')]
    private string $profileSetupSessionId;

    #[ORM\Column(name: 'step_key', length: 64)]
    private string $stepKey;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $profileSetupSessionId, string $stepKey)
    {
        $this->profileSetupSessionId = $profileSetupSessionId;
        $this->stepKey = $stepKey;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getProfileSetupSessionId(): string
    {
        return $this->profileSetupSessionId;
    }

    public function getStepKey(): string
    {
        return $this->stepKey;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

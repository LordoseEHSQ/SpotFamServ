<?php

declare(strict_types=1);

namespace App\Module\Scan\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reader_claim')]
#[ORM\UniqueConstraint(name: 'uniq_reader_claim_code_hash', columns: ['claim_code_hash'])]
class ReaderClaim
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_EXPIRED = 'expired';

    private const MAX_ACTIVATION_ATTEMPTS = 5;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'claim_code_hash', length: 64)]
    private string $claimCodeHash;

    #[ORM\Column(name: 'reader_id', length: 64, nullable: true)]
    private ?string $readerId = null;

    #[ORM\Column(name: 'reader_name', length: 255, nullable: true)]
    private ?string $readerName = null;

    #[ORM\Column(name: 'fw_channel', length: 32)]
    private string $fwChannel;

    #[ORM\Column(name: 'activation_attempts')]
    private int $activationAttempts = 0;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'used_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $claimCodeHash, \DateTimeImmutable $expiresAt, ?string $readerName, string $fwChannel)
    {
        $this->claimCodeHash = $claimCodeHash;
        $this->expiresAt = $expiresAt;
        $this->readerName = $readerName;
        $this->fwChannel = $fwChannel;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getClaimCodeHash(): string
    {
        return $this->claimCodeHash;
    }

    public function getReaderId(): ?string
    {
        return $this->readerId;
    }

    public function getReaderName(): ?string
    {
        return $this->readerName;
    }

    public function getFirmwareChannel(): string
    {
        return $this->fwChannel;
    }

    public function getActivationAttempts(): int
    {
        return $this->activationAttempts;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function status(\DateTimeImmutable $now): string
    {
        if ($this->usedAt !== null) {
            return self::STATUS_CLAIMED;
        }

        if ($this->expiresAt <= $now) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_PENDING;
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return $this->usedAt === null && $this->expiresAt > $now;
    }

    public function hasTooManyActivationAttempts(): bool
    {
        return $this->activationAttempts >= self::MAX_ACTIVATION_ATTEMPTS;
    }

    public function recordFailedActivationAttempt(): void
    {
        $this->activationAttempts++;
    }

    public function markUsed(string $readerId): void
    {
        $this->readerId = $readerId;
        $this->usedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}

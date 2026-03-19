<?php

declare(strict_types=1);

namespace App\Module\Spotify\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'spotify_account_link')]
#[ORM\UniqueConstraint(name: 'uniq_family_profile', columns: ['family_profile_id'])]
class SpotifyAccountLink
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'family_profile_id', type: 'uuid', unique: true)]
    private string $familyProfileId;

    #[ORM\Column(name: 'spotify_user_id', length: 255)]
    private string $spotifyUserId;

    /** @var string Encrypted at rest via spotify_encrypted_string type */
    #[ORM\Column(name: 'access_token', type: 'spotify_encrypted_string')]
    private string $accessToken;

    /** @var string Encrypted at rest via spotify_encrypted_string type */
    #[ORM\Column(name: 'refresh_token', type: 'spotify_encrypted_string')]
    private string $refreshToken;

    #[ORM\Column(name: 'scopes', length: 512, nullable: true)]
    private ?string $scopes = null;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'spotify_display_name', length: 255, nullable: true)]
    private ?string $spotifyDisplayName = null;

    #[ORM\Column(name: 'last_validated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastValidatedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $familyProfileId,
        string $spotifyUserId,
        string $accessToken,
        string $refreshToken,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->familyProfileId = $familyProfileId;
        $this->spotifyUserId = $spotifyUserId;
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->expiresAt = $expiresAt;
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

    public function getSpotifyUserId(): string
    {
        return $this->spotifyUserId;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getScopes(): ?string
    {
        return $this->scopes;
    }

    public function setScopes(?string $scopes): void
    {
        $this->scopes = $scopes;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function setRefreshToken(string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function updateTokens(string $accessToken, \DateTimeImmutable $expiresAt, ?string $scopes = null): void
    {
        $this->accessToken = $accessToken;
        $this->expiresAt = $expiresAt;
        if ($scopes !== null) {
            $this->scopes = $scopes;
        }
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getSpotifyDisplayName(): ?string
    {
        return $this->spotifyDisplayName;
    }

    public function setSpotifyDisplayName(?string $name): void
    {
        $this->spotifyDisplayName = $name;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getLastValidatedAt(): ?\DateTimeImmutable
    {
        return $this->lastValidatedAt;
    }

    public function markValidated(?string $displayName = null): void
    {
        $this->lastValidatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($displayName !== null) {
            $this->spotifyDisplayName = $displayName;
        }
        $this->updatedAt = $this->lastValidatedAt;
    }
}

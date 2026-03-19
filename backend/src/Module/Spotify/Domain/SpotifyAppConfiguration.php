<?php

declare(strict_types=1);

namespace App\Module\Spotify\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Systemweite Spotify-App-Konfiguration (Singleton).
 * Enthält Client-Credentials für die zentrale Spotify-App-Integration.
 * Pro Installation existiert genau ein aktiver Datensatz.
 *
 * Trennung: Diese Entity verwaltet App-Credentials (Client ID/Secret),
 *           nicht die teilnehmerbezogenen OAuth-Tokens (→ SpotifyAccountLink).
 */
#[ORM\Entity]
#[ORM\Table(name: 'spotify_app_configuration')]
class SpotifyAppConfiguration
{
    public const STATUS_UNCONFIGURED = 'unconfigured';
    public const STATUS_CONFIGURED   = 'configured';
    public const STATUS_VALIDATED    = 'validated';
    public const STATUS_ERROR        = 'error';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'spotify_client_id', length: 255, nullable: true)]
    private ?string $spotifyClientId = null;

    /** Encrypted at rest via spotify_encrypted_string Doctrine type */
    #[ORM\Column(name: 'spotify_client_secret', type: 'spotify_encrypted_string', nullable: true)]
    private ?string $spotifyClientSecret = null;

    #[ORM\Column(name: 'redirect_uri', length: 512, nullable: true)]
    private ?string $redirectUri = null;

    #[ORM\Column(name: 'scope_defaults', length: 1024, nullable: true)]
    private ?string $scopeDefaults = null;

    #[ORM\Column(name: 'config_status', length: 32, options: ['default' => 'unconfigured'])]
    private string $configStatus = self::STATUS_UNCONFIGURED;

    #[ORM\Column(name: 'last_check_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastCheckAt = null;

    #[ORM\Column(name: 'last_check_note', length: 512, nullable: true)]
    private ?string $lastCheckNote = null;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSpotifyClientId(): ?string
    {
        return $this->spotifyClientId;
    }

    public function setSpotifyClientId(?string $clientId): void
    {
        $this->spotifyClientId = $clientId;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getSpotifyClientSecret(): ?string
    {
        return $this->spotifyClientSecret;
    }

    public function setSpotifyClientSecret(?string $secret): void
    {
        $this->spotifyClientSecret = $secret;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getRedirectUri(): ?string
    {
        return $this->redirectUri;
    }

    public function setRedirectUri(?string $uri): void
    {
        $this->redirectUri = $uri;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getScopeDefaults(): ?string
    {
        return $this->scopeDefaults;
    }

    public function setScopeDefaults(?string $scopes): void
    {
        $this->scopeDefaults = $scopes;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getConfigStatus(): string
    {
        return $this->configStatus;
    }

    public function setConfigStatus(string $status): void
    {
        $this->configStatus = $status;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getLastCheckAt(): ?\DateTimeImmutable
    {
        return $this->lastCheckAt;
    }

    public function getLastCheckNote(): ?string
    {
        return $this->lastCheckNote;
    }

    public function recordCheck(bool $success, string $note): void
    {
        $this->lastCheckAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->lastCheckNote = $note;
        $this->configStatus = $success ? self::STATUS_VALIDATED : self::STATUS_ERROR;
        $this->updatedAt = $this->lastCheckAt;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isComplete(): bool
    {
        return $this->spotifyClientId !== null && $this->spotifyClientId !== ''
            && $this->spotifyClientSecret !== null && $this->spotifyClientSecret !== ''
            && $this->redirectUri !== null && $this->redirectUri !== '';
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

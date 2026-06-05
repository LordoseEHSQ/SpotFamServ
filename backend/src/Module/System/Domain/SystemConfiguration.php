<?php

declare(strict_types=1);

namespace App\Module\System\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Systemweite Betriebs-Konfiguration (Singleton, D-029).
 *
 * Eine typisierte Entity statt mehrerer pro-Domäne-Entities: Reader-Netzwerk
 * (WLAN/Backend/OTA) + Frontend-URL. Maschinen-Keys bleiben bewusst env-kanonisch
 * (D-030) und sind NICHT Teil dieser Entity.
 *
 * Secrets (wifiPassword) sind via Doctrine-Type `spotify_encrypted_string`
 * verschlüsselt at rest (kein neues Crypto). Pro Installation genau ein aktiver Datensatz.
 */
#[ORM\Entity]
#[ORM\Table(name: 'system_configuration')]
class SystemConfiguration
{
    public const OTA_CHANNELS = ['stable', 'beta', 'dev'];
    public const DEFAULT_OTA_CHANNEL = 'stable';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'wifi_ssid', length: 64, nullable: true)]
    private ?string $wifiSsid = null;

    /** Encrypted at rest via spotify_encrypted_string Doctrine type. */
    #[ORM\Column(name: 'wifi_password', type: 'spotify_encrypted_string', nullable: true)]
    private ?string $wifiPassword = null;

    #[ORM\Column(name: 'backend_base_url', length: 255, nullable: true)]
    private ?string $backendBaseUrl = null;

    #[ORM\Column(name: 'ota_channel', length: 32, options: ['default' => self::DEFAULT_OTA_CHANNEL])]
    private string $otaChannel = self::DEFAULT_OTA_CHANNEL;

    #[ORM\Column(name: 'frontend_url', length: 512, nullable: true)]
    private ?string $frontendUrl = null;

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

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getWifiSsid(): ?string
    {
        return $this->wifiSsid;
    }

    public function setWifiSsid(?string $ssid): void
    {
        $this->wifiSsid = $ssid;
        $this->touch();
    }

    public function getWifiPassword(): ?string
    {
        return $this->wifiPassword;
    }

    public function setWifiPassword(?string $password): void
    {
        $this->wifiPassword = $password;
        $this->touch();
    }

    public function getBackendBaseUrl(): ?string
    {
        return $this->backendBaseUrl;
    }

    public function setBackendBaseUrl(?string $url): void
    {
        $this->backendBaseUrl = $url;
        $this->touch();
    }

    public function getOtaChannel(): string
    {
        return $this->otaChannel;
    }

    /**
     * Setzt den OTA-Kanal. Ungültige Werte fallen auf den Default zurück
     * (Validierung gehört in den UseCase; hier defensiv).
     */
    public function setOtaChannel(string $channel): void
    {
        $this->otaChannel = in_array($channel, self::OTA_CHANNELS, true)
            ? $channel
            : self::DEFAULT_OTA_CHANNEL;
        $this->touch();
    }

    public function getFrontendUrl(): ?string
    {
        return $this->frontendUrl;
    }

    public function setFrontendUrl(?string $url): void
    {
        $this->frontendUrl = $url;
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Vollständig für die Flash-Zeit-NVS-Injektion (Phase C): WLAN + Backend-URL gesetzt.
     */
    public function isReaderNetworkComplete(): bool
    {
        return $this->wifiSsid !== null && $this->wifiSsid !== ''
            && $this->wifiPassword !== null && $this->wifiPassword !== ''
            && $this->backendBaseUrl !== null && $this->backendBaseUrl !== '';
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

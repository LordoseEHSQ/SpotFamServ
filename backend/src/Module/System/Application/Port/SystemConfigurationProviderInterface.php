<?php

declare(strict_types=1);

namespace App\Module\System\Application\Port;

/**
 * Liefert die effektiven System-Konfigurationswerte pro Request (kein Prozess-Cache,
 * damit ein UI-Save ohne Neustart greift). Präzedenz: DB-Wert (falls gesetzt), sonst
 * Env-/Default-Fallback – PRO FELD (D-029; kein Whole-Unit-Mix wie D-011, da hier keine
 * gekoppelten Secret-Paare existieren).
 */
interface SystemConfigurationProviderInterface
{
    /** Frontend-Basis-URL (für OAuth-Redirects). DB, sonst Env `FRONTEND_URL`. */
    public function getFrontendUrl(): string;

    /** OTA-Kanal für Reader-Firmware. DB, sonst Default `stable`. */
    public function getOtaChannel(): string;

    /** Backend-Basis-URL für Reader/NVS-Injektion. DB, sonst `null` (kein Env-Default). */
    public function getBackendBaseUrl(): ?string;

    /** WLAN-SSID für die Flash-Zeit-NVS-Injektion. Nur DB. */
    public function getWifiSsid(): ?string;

    /** WLAN-Passwort (Klartext, entschlüsselt) für die NVS-Injektion. Nur DB. NIE loggen/ausliefern. */
    public function getWifiPassword(): ?string;
}

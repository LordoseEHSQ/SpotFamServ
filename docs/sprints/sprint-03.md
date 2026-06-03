# Sprint 3 – Multi-Raum & Reader-Lifecycle

**Milestone:** Sprint 3 – Multi-Raum & Reader-Lifecycle (#4) · **Status:** Geschlossen (retroaktiv, D-S4-VER)
**Releases:** `v0.2.3` (Spotify-Status-Fix) · `v0.2.4` (Interim Sprint 3) · `v0.2.5` (Playlist-Binding-Fix)
**Zeitraum:** 2026-06-02 (kompakt, ein Chat-Sprint mit Subagenten-Schwarm)
**Branch:** `feat/sprint-03-reader-lifecycle` → Squash-Merge nach `main` via PR #37 (#38)

---

## Sprint-Ziel (Retrospektive)

Reader-Lifecycle vollständig (Reader-Entität, Pro-Reader-API-Keys, Reader→Gerät-Mapping),
Pi-Leser (PN532/HW-147) operational (Daemon + Scan-to-Create), Terminologie auf generisches
Spotify-Connect-Gerät umgestellt.

**Nicht erreicht (hardware-blockiert):** Pi-Migration (Multi-Raum-E2E) und Integration der
Reader→Gerät-Logik in den Scan-Flow. Beides in Sprint 4 (WP1 + WP3) absorbiert.

---

## Acceptance Criteria – Ergebnis

| Kriterium | Status | Anmerkung |
|---|---|---|
| #32 Reader→Box-Mapping Logik | ✅ | SetReaderDefaultDevice, reader_device.default_spotify_device_id, v0.2.4 |
| #34 Pi-Leser + Scan-to-Enroll | ✅ | pi_reader.py, UID-Lookup, Scan-to-Create; v0.2.4 + v0.2.5-Fix |
| #35 Pro-Reader-API-Keys | ✅ | GenerateReaderApiKey, RevokeReaderApiKey, ReadersPage im Frontend, v0.2.4 |
| #36 Terminologie Wobie→Connect | ✅ | Konsistent in Backend + Frontend + Docs, v0.2.4 |
| #33 Pi-Migration + Multi-Raum-E2E | ⏭️ | Hardware-blockiert; in Sprint 4 WP1+WP3 absorbiert |
| CI grün | ✅ | PHPStan L8, PHPUnit, oasdiff non-breaking, Frontend, Trivy |

---

## Releases

### v0.2.3 – Spotify-Status refresh-getrieben (#25)
Spotify-Verbindungsstatus spiegelt echten Re-Auth-Bedarf (nicht Access-Token-Takt).
- `needs_reauth`-Flag auf `spotify_account_link`; 3 Status: `connected|reauth_required|not_connected`.
- `SpotifyTokenManager` setzt Flag nur bei `invalid_grant`; transiente Fehler ignoriert.
- Frontend: `expired` → `reauth_required` in allen Consumern.
- Decision D-014.

### v0.2.4 – Sprint 3 Interim (Reader-Lifecycle, Pi-Leser, Pro-Reader-Keys, Wobie→Connect)
Kern-Sprint-3-Lieferung:
- **Reader-Lifecycle (#32):** `ReaderDevice`-Entität mit `default_spotify_device_id`, UseCase
  `SetReaderDefaultDevice`, Backend-Endpunkt `PUT /api/v1/readers/{id}/default-device`,
  Migration `Version20260602140000_reader_default_device`.
- **Pi-Leser + Scan-to-Create (#34):** `firmware/pi_reader/pi_reader.py` committet (PN532/HW-147),
  `LookupRfidCardByUid` UseCase (globaler UID-Lookup ohne Profil-Kontext),
  `RfidCardLookupController`, Scan-to-Create am Profil via neuem `/rfid-cards/lookup?uid=`-Endpunkt.
- **Pro-Reader-API-Keys (#35):** `GenerateReaderApiKey`, `RevokeReaderApiKey`, `validateReaderAuth`
  auf `ReaderDevice::validateApiKey` verdrahtet. Frontend `ReadersPage` zeigt Keys.
- **Terminologie (#36):** „Wobie Box" → generisches Spotify-Connect-Gerät in Backend, Frontend, Docs.
- L-015 (transientes 500 im Deploy-Fenster).

### v0.2.5 – Playlist-Bindung aus echter Spotify-Bibliothek (#34)
Fix: Playlist-Bindings wurden aus der gespeicherten Datenbank-Bibliothek statt aus der echten
Spotify-Bibliothek des Profils geholt. Karten-Binding-UI zeigt jetzt aktuelle Spotify-Playlists.
Footer-Versionsfix (L-016).

---

## Offene Altlasten / Deferred

- **#33 Pi-Migration + Multi-Raum-E2E:** Reader→Gerät-Logik in `ProcessScan`/`StartPlayback`
  wurde NICHT verdrahtet (nur Schema/UseCase). Sprint 4 WP1 baut die vollständige Gerätewahl.
- **Pi-Daemon systemd:** `pi_reader.py` ist committet, aber als `setsid`-Prozess laufend (kein
  systemd). Sprint 4 WP3 bringt `spotfam-reader.service`.
- **Entprellung:** Pi-Daemon feuert im Dauertakt (788× dieselbe UID). Sprint 4 WP3 fixt.
- **Sprint 2 E2E (blockiert):** #8 OAuth-Consent via Tunnel + #10 ESP32-Flash weiterhin
  User/Hardware-blockiert. Bleiben in Sprint 2 Milestone (#3) offen.

---

## Lessons (Sprint 3)

- L-015: Transientes 500 im Deploy-Fenster (Schema migriert, aber Code noch nicht deployed).
- L-016: Footer-Versionsfix (implizit in v0.2.5).

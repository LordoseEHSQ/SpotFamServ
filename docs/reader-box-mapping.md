# Reader→Box-Mapping (Multi-Raum)

Stand: 2026-06-02 · Decision: D-015 · Plan: `tasks/plan-reader-box-mapping.md`

## Zweck
Standardmäßig bestimmt das **Profil der gescannten Karte** den Lautsprecher
(`family_profile.default_spotify_device_id`). Für Multi-Raum („Karte spielt dort, wo ich sie
scanne") kann jeder Leser eine eigene **Box** bekommen. Liegt eine Reader-Box vor, gewinnt sie;
sonst greift weiterhin der Profil-Default (rückwärtskompatibel).

## Verhalten
- **Auflösungsreihenfolge beim Scan** (`ProcessScan`):
  1. Reader auflösen; unbekannter Reader → **automatisch registriert** (sichtbar im UI, kein API-Key).
  2. Karte → Profil → Playlist-Binding auflösen (unverändert).
  3. Zielgerät = Reader-Box (`reader_device.default_spotify_device_id`) **falls gesetzt**, sonst `null`
     → `StartPlayback` nutzt den Profil-Default.
- **Stale-Box-Robustheit:** Spotify vergibt nach Box-Reconnect ggf. eine neue Device-ID. `StartPlayback`
  re-resolved bei explizitem Gerät einmalig per **Name** (`default_device_name`) und spielt auf der
  frischen ID. (Keine Persistenz der neuen ID auf MVP-Stand – Re-Resolve passiert bei Bedarf erneut.)
- **Next/Previous** (`ProcessReaderControl`) wirken über den `PlaybackSessionStore` auf das Profil der
  letzten Karte → dessen Account spielt auf genau einem Gerät, daher kein zusätzliches Device-Targeting nötig.

## Harte Spotify-Grenze
Ein Spotify-**Account** spielt nur auf **einem** Gerät gleichzeitig. Gleichzeitiges Multi-Raum geht
nur über **verschiedene Profile/Accounts** (je Profil ein eigenes Premium-Konto). Dieselbe
Karte/dasselbe Profil kann nicht parallel in zwei Räumen spielen – der zweite Scan übernimmt.
Voraussetzung: jede Ziel-Box ist ein **Spotify-Connect-Gerät** (kein reiner Bluetooth-Lautsprecher).

## API
- `GET /api/v1/readers` → `{ items: Reader[] }`
- `PUT /api/v1/readers/{readerId}/default-device` — Body `{ "device_id": "<spotify_device_id>", "device_name": "<optional>" }` → `Reader`
- `DELETE /api/v1/readers/{readerId}/default-device` → `Reader` (Mapping entfernt)

`Reader`:
```json
{
  "id": "uuid|null",
  "reader_id": "wohnzimmer-1",
  "name": null,
  "default_spotify_device_id": "abc123|null",
  "default_device_name": "Wohnzimmer Box|null"
}
```
Fehler: unbekannter `readerId` → `404` (Leser registriert sich beim ersten Scan automatisch).
`device_id` leer/fehlend bei `PUT` → `400`.

## Bedienung (UI)
„RFID-Leser" → Leser wählen → „Box zuweisen" → Lautsprecher aus dem Geräte-Inventar wählen
(zuvor unter „Lautsprecher & Geräte" Discovery ausführen). „Entfernen" setzt den Leser zurück auf Profil-Default.

## Offene Punkte
- Persistenz der re-resolveten Box-ID auf dem Reader (Optimierung, derzeit Re-Resolve bei Bedarf).
- Herkunft der Reader-Zeilen ist Auto-Register beim Scan (D-R1 Option A); explizite Registrierung folgt
  mit dem Provisioning-Plan (`tasks/plan-esp-ota-perreader-keys.md`).

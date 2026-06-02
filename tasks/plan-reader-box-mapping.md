# Plan: Reader→Box-Mapping (Multi-Raum)

**Erstellt:** 2026-06-02
**Status:** Plan – wartet auf Bestaetigung, NICHT umgesetzt

## Scope
Jeder Reader (`reader_device`) bekommt ein optionales Standard-Spotify-Geraet
(Raum-Box). Beim Karten-Scan spielt die Playlist auf der **Box des Readers**, nicht
mehr zwingend auf dem Profil-Default. Faellt kein Reader-Mapping vor, bleibt das
heutige Verhalten (Profil-Default) erhalten → rueckwaertskompatibel.

## Bestaetigte Entscheidungen (User, 2026-06-02)
- Lautsprecher soll vom **Standort des Readers** bestimmt werden (Multi-Raum).
- Alle Zielboxen sind/werden **Spotify-Connect-faehig** → kein Zusatzhardware-Pfad noetig.
- **Jedes Profil hat ein eigenes Spotify-Premium-Konto** → echtes *gleichzeitiges* Multi-Raum-Playback ist machbar (verschiedene Accounts spielen parallel auf verschiedenen Boxen).

## Betroffene Bereiche
- `Scan/` (Domain `ReaderDevice`, `ProcessScan`, `ProcessReaderControl`) — Reader bekommt Default-Device; Scan/Skip zielen darauf.
- `Spotify/` (`StartPlayback`, `SkipToNext/Previous`) — akzeptiert bereits explizites `deviceId` (Z. 30-32); muss nur befuellt werden.
- DB-Schema — `reader_device` um `default_spotify_device_id` + `default_device_name` erweitern (Migration, type `schema`).
- HTTP/Frontend — Reader-Liste + „Box zuweisen" (analog D-009 `SetDefaultDevice` fuer Profile).

## 4-Lens-Analyse (Pflicht)

### Lens 1 – Runtime & Sprache
- Keine neue Runtime. PHP 8.5 / Symfony 7.4, Doctrine-Migration wie bestehende.

### Lens 2 – Frameworks & Abhaengigkeiten
- Keine neuen Dependencies. Wiederverwendung vorhandener Device-Discovery (`Device/`, `SpotifyDevice`) fuer die Box-Auswahl.

### Lens 3 – Build, CI/CD & Tooling
- Eine additive Migration. OpenAPI/oasdiff: neue Felder in Reader-Response + neuer/erweiterter Endpunkt → `openapi.yaml` pflegen (Gate).
- Neue Tests: `ProcessScanTest` (Reader mit/ohne Mapping), Mapping-UseCase-Test.

### Lens 4 – Security & Compliance
- Reader→Device-Zuweisung ist Admin-Aktion (gleiche Auth wie restliche Admin-API). Kein neues Secret.
- Kein Klartext-Token betroffen; Device-IDs sind nicht sensibel.

## Cross-Module Antworten
1. **Upstream:** ESP-Scan speist `ProcessScan`. Aenderung additiv (dritter Parameter an `StartPlayback`), bricht keinen Producer.
2. **Downstream:** Spotify Web API konsumiert die Device-ID. `StartPlayback` re-resolved stale IDs bereits per Name (Z. 43-53) – dieselbe Logik muss auch fuer die **Reader**-Box greifen (sonst bricht Playback nach Box-Reconnect). Konkreter Arbeitspunkt.
3. **Audit:** Reader-Default ist Config-State → Decision-Log-Eintrag (Schema + neuer Endpunkt).
4. **API-Vertrag:** Response-Shape von Reader-Objekten aendert sich (neue Felder) + neuer PUT-Endpunkt → openapi.yaml + Frontend-Consumer aktualisieren.
5. **Feature-Flags:** nicht noetig; rein additiv und rueckwaertskompatibel.

## Geplante Umsetzung (nach Bestaetigung)
1. **Schema (separater Commit, type `schema`):** `reader_device` + `default_spotify_device_id VARCHAR(255) NULL`, `default_device_name VARCHAR(255) NULL`.
2. **Domain:** `ReaderDevice` um beide Felder + Setter/Getter (analog `FamilyProfile::setDefaultDevice`).
3. **Scan-Pfad:** `ProcessScan` ermittelt das Reader-Geraet (schon vorhanden, Z. 42-44); wenn `defaultSpotifyDeviceId` gesetzt → an `StartPlayback(..., deviceId)` durchreichen, sonst `null` (heutiges Verhalten).
4. **Stale-ID-Robustheit:** Re-Resolve-per-Name (heute nur fuer Profil-Default) auch fuer die Reader-Box anwenden – sonst bricht es nach Box-Reconnect (vgl. Cross-Module #2).
5. **Skip-Pfad:** `ProcessReaderControl` zielt auf das Reader-Geraet statt nur „aktive Session". (Pruefen: Spotify-Skip wirkt auf aktives Geraet des Accounts – ggf. `transferPlayback` vor Skip noetig.)
6. **UseCase + Endpunkt:** `SetReaderDefaultDevice` + `PUT /api/v1/readers/{readerId}/default-device` (Muster D-009). Plus `GET /api/v1/readers` (Liste; `findAll` existiert bereits im Repo).
7. **Frontend:** Reader-Seite (Liste aller Reader) mit „Box zuweisen" (Device-Discovery-Auswahl wiederverwenden).
8. **OpenAPI + Doku** ergaenzen.

## Akzeptanzkriterien
1. Reader A (Box X zugewiesen) + Reader B (Box Y): dieselbe Karte spielt je nach Reader auf X bzw. Y.
2. Reader ohne Mapping: Verhalten unveraendert (Profil-Default-Box).
3. Wird die Box-ID stale (Reconnect), spielt es nach Re-Resolve-per-Name trotzdem.
4. `GET /api/v1/readers` liefert alle Reader inkl. zugewiesener Box (id+name).
5. `PUT …/default-device` setzt/entfernt das Mapping; im Scan sofort wirksam.

## Definition of Done
- [ ] Migration additiv + reversibel; Constraints/Index verifiziert
- [ ] `ProcessScanTest`: Reader mit/ohne Mapping, Stale-Re-Resolve
- [ ] Endpunkt-Tests (Liste, set/clear) inkl. Response-Shape
- [ ] openapi.yaml + Frontend-Consumer aktualisiert (kein oasdiff-Bruch)
- [ ] Bestehende Tests gruen; Doku (`docs/`) ergaenzt
- [ ] Cross-Module-Checkliste beantwortet

## Risiken / Offene Fragen
- **Spotify-Grenze (geklaert):** Ein Spotify-**Account** spielt nur auf **einem** Geraet gleichzeitig. Da jedes Profil ein **eigenes** Konto hat (User 2026-06-02), funktioniert paralleles Multi-Raum ueber verschiedene Profile. Verbleibende Grenze: **dieselbe Karte/dasselbe Profil** kann nicht gleichzeitig in zwei Raeumen spielen – der zweite Scan uebernimmt dann die Wiedergabe dieses Accounts.
- **Offene Decision D-R1 (blockiert Schritt 6):** Woher kommen die `reader_device`-Zeilen, die man mappen kann?
  - A) **Auto-Upsert beim ersten Scan** (Reader taucht nach erstem Scan in der Liste auf, dann Box zuweisen). Empfehlung: niedrigste Friktion.
  - B) Nur explizite Registrierung (haengt an Plan Pi-Leser/Provisioning).
  → Empfehlung A; final mit Plan „Pi-Leser/OTA" abstimmen (Konsistenz reader_id-Vergabe).
- Skip auf gezielter Box: pruefen, ob `transferPlayback` vor `next/previous` noetig ist.

## Verifikations-Log
{Beim Umsetzen ausfuellen}

## Abgeschlossen
{Datum + Summary wenn fertig}

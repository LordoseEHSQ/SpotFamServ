# Plan: Spotify-Status – Refresh-getrieben statt Access-Token-Takt (#25)

**Erstellt:** 2026-06-02
**Status:** Abgeschlossen (umgesetzt, Decision D-014 = Option B akzeptiert)
**Branch/Worktree:** `feat/spotify-status-reauth` / `../SpotFamServ-status-reauth`
**Issue:** #25 · **Decision:** D-014 (vorzulegen) · **Lessons:** L-004 (APP_SECRET/Token), L-011 (verifizieren am Effekt)

## Scope
Der Spotify-Verbindungsstatus zeigt nach Ablauf des 1h-Access-Tokens fälschlich „abgelaufen",
obwohl der `SpotifyTokenManager` automatisch per Refresh-Token erneuert. Status soll die **echte**
Re-Auth-Notwendigkeit abbilden (`connected | reauth_required | not_connected`), aus **einer** Quelle,
inkl. persistiertem `needs_reauth`-Flag, das bei echtem Refresh-Fehler gesetzt und bei Re-Consent
gelöscht wird. Frontend-Labels + OpenAPI + Tests mitziehen.

## Betroffene Bereiche
- `Spotify/Domain/SpotifyAccountLink` — neues Feld `needs_reauth` (bool) + Getter/Setter, `markNeedsReauth()/clearNeedsReauth()`.
- **Migration** (separater Commit, type `schema`) — Spalte `spotify_account_link.needs_reauth BOOLEAN NOT NULL DEFAULT false`.
- `Spotify/Infrastructure/Spotify/SpotifyTokenManager` — `refreshAndPersist`: bei `invalid_grant`/`SpotifyTokenInvalidException` `needs_reauth=true` setzen + persistieren + ActivityLog, dann rethrow; bei Erfolg `needs_reauth=false`.
- `Spotify/Application/ExchangeSpotifyCode` — nach erfolgreichem Consent `needs_reauth=false`.
- `Spotify/Application/GetSpotifyStatus` (+`GetSpotifyStatusResult`) — **einzige** Status-Quelle: not_connected / reauth_required / connected.
- `FamilyProfile/Infrastructure/Http/FamilyProfileController::resolveSpotifyStatus` — **Duplikat entfernen**, stattdessen `GetSpotifyStatus` (bzw. gemeinsamen Resolver) nutzen.
- Frontend: `api/endpoints/profiles.ts` (Typ-Enum), `pages/ProfilesPage.tsx`, `pages/ProfileDetailPage.tsx`, `pages/DashboardPage.tsx`, `components/music/{MusicTab,MiniPlayer,PlaylistDetail}.tsx`, `features/setup-wizard/steps/StepSpotifyConnect.tsx`.
- `backend/openapi.yaml` (Regenerat), `CHANGELOG.md`, `tasks/decisions.md` (D-014), ggf. `tasks/lessons.md`.

## Cross-Module Antworten
1. **Upstream:** Speist wird der Status vom OAuth/Token-Flow (`ExchangeSpotifyCode`, `SpotifyTokenManager`).
   Änderung am `SpotifyAccountLink` (neues Feld) — Producer sind genau diese beiden + Migration. Kein anderer Writer.
2. **Downstream:** Konsumenten von `spotify_status`: Frontend (8 Stellen) + Setup-Wizard-Step. Enum-Wert
   `expired` entfällt → **alle** Consumer im selben PR anpassen, sonst toter/zombie-Zweig.
3. **Audit:** Refresh-Fehler → ActivityLog-Eintrag (neu, z. B. `spotify_reauth_required`). State-Änderung am Link.
4. **API-Vertrag:** `spotify_status`-Enum ändert sich (`expired`→raus, `reauth_required`→rein). OpenAPI
   regenerieren; oasdiff prüfen (Entfernen eines Response-Enum-Werts ist i. d. R. **nicht** breaking für
   Clients; falls oasdiff doch ERR meldet → bewusst bewerten, im PR dokumentieren).
5. **Feature-Flags:** Nicht nötig (reine Status-Semantik, keine riskante Verhaltensänderung am Playback).

## Akzeptanzkriterien
1. Profil mit gültigem Refresh-Token = `connected`, auch bei abgelaufenem Access-Token-Zeitstempel.
2. Echter Refresh-Fehler (`invalid_grant`) → persistent `reauth_required`; Re-Consent → `connected`.
3. Genau **eine** Status-Berechnung (keine Controller/UseCase-Duplikation).
4. Frontend zeigt kein „Abgelaufen" mehr im Normalfall; `reauth_required` = klares „Neu verbinden nötig".
5. OpenAPI regeneriert, Enum aktualisiert, CI inkl. oasdiff grün.
6. Tests: TokenManager setzt/cleart `needs_reauth`; Resolver liefert die 3 Zustände.

## Definition of Done
- [ ] Implementierung komplett (Domain+Migration getrennt, TokenManager, Resolver, Controller, Frontend)
- [ ] Tests geschrieben und grün (inkl. Regressions-Test „abgelaufen-aber-connected")
- [ ] OpenAPI regeneriert; oasdiff grün; restliche 5 Checks grün
- [ ] Alle `spotify_status`-Consumer aktualisiert (keine `expired`-Referenz mehr)
- [ ] Cross-Module-Checkliste beantwortet
- [ ] Docs: CHANGELOG, D-014, ggf. Lesson; Plan „Abgeschlossen" gefüllt
- [ ] PR squash-merge → Release-Tag (Patch, z. B. `v0.2.3`) → Pi verifizieren (L-011: am Effekt prüfen)

## Decision D-014 (vorzulegen) – Status-Semantik
**Kontext:** Status muss Re-Auth-Bedarf abbilden, nicht den Access-Token-Takt.
**Optionen:**
- A) Ohne Persistenz: „Refresh-Token vorhanden" ⇒ `connected`; Re-Auth-Bedarf erst sichtbar, wenn eine echte Aktion scheitert. Vorteile: kein Schema. Nachteile: zeigt kaputten Refresh-Token erst verspätet.
- B) **Persistiertes `needs_reauth`-Flag**, gesetzt bei echtem Refresh-Fehler, gelöscht bei Re-Consent/erfolgreichem Refresh. Vorteile: korrekt & proaktiv sichtbar. Nachteile: Mini-Schema-Migration + Wiring.
**Empfehlung:** **B** (akkurat, surfacet echten Bedarf). **Status:** Accepted (User-Freigabe „sauber zu Ende").

## Risiken / Blind Spots
- **R1 oasdiff:** Enum-Änderung könnte als breaking gemeldet werden → ggf. bewusst akzeptieren/anpassen.
- **R2 Migration auf dem Pi:** Default `false` für Bestandszeilen → bestehende verbundene Profile bleiben `connected`. Backup vor Migration (D-007) greift via `pi-backup.sh`.
- **R3 Doppellogik übersehen:** Es gibt **zwei** Status-Berechnungen (UseCase + Controller). Beide auf eine Quelle ziehen, sonst Drift bleibt.
- **R4 Refresh-Fehler-Klassifizierung:** Nur „dauerhaft ungültig" (`invalid_grant`) als `reauth_required` werten — **nicht** transiente Netzfehler/5xx (sonst false-positive Re-Auth-Aufforderung).
- **R5 APP_SECRET-Wechsel (L-004):** Macht gespeicherte Tokens unlesbar → würde als Refresh-Fehler erscheinen; korrekt als `reauth_required` (Re-Consent nötig). Im Doc erwähnen.

## Subagenten-Plan (für den Ausführungs-Chat)
- A (`explore`): Backend-Refresh-Fehlerpfade — wo wird `SpotifyTokenInvalidException`/`invalid_grant` geworfen/gemappt (`SpotifyHttpApiClient.refreshToken`, `ExceptionSubscriber`), um R4 sauber zu treffen.
- B (`explore`): Alle Frontend-Consumer von `spotify_status` + Setup-Wizard-Verhalten verifizieren (Vollständigkeit AK4).
- Seriell: Domain/Migration → TokenManager/Resolver → Controller-Entdopplung → Frontend → OpenAPI/Tests.

## Verifikations-Log
- Lokal (wie CI): PHPStan Level 8 = OK; PHPUnit = 28 Tests/75 Assertions OK (inkl. 3 neue: Refresh-Fehler→`needs_reauth`,
  erfolgreicher Refresh cleart Flag, Resolver-3-Zustände); Frontend `tsc -b && vite build` = OK; vitest = no test files.
- OpenAPI (`nelmio:apidoc:dump`) gegen Baseline: **0 Zeilen Diff** → `spotify_status` ist im Spec nicht enumeriert,
  daher kein API-Drift (oasdiff unkritisch). AK5 „OpenAPI regeneriert" = erfüllt (keine Änderung nötig).
- R4 bestätigt: `SpotifyHttpApiClient.refreshToken` wirft bei 401/`invalid_grant` `SpotifyTokenInvalidException`,
  bei 5xx/transient `SpotifyApiException` → nur Ersteres setzt `needs_reauth` (kein false-positive).

## Abgeschlossen
- Umgesetzt auf `feat/spotify-status-reauth`: Domain-Flag + Migration (separat), TokenManager set/clear,
  ExchangeSpotifyCode clear, `GetSpotifyStatus::resolve()` als einzige Quelle (Controller-Duplikat entfernt),
  Frontend-Enum + alle Consumer + Labels + ActivityLog-Label. Status: `connected | reauth_required | not_connected`.
- Release-Tag/Pi-Verifikation: siehe CHANGELOG v0.2.3 + Pi-Effekt-Check (L-011).

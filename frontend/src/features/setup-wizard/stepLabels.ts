/**
 * Human-readable labels for wizard step keys.
 * Single source of truth — imported by WizardStepper and StepSummary.
 */
export const STEP_LABELS: Record<string, string> = {
  profile: 'Profil',
  spotify_connect: 'Spotify verbinden',
  spotify_validate: 'Verbindung prüfen',
  devices: 'Geräte',
  default_speaker: 'Lautsprecher',
  playback_test: 'Testwiedergabe',
  playlist: 'Playlist',
  rfid_bind: 'RFID-Karte',
  summary: 'Abschluss',
};

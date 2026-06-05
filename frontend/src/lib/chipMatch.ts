// Chip-Familien-Abgleich fuer die Reader-Station (BERATEND).
//
// Das autoritative Gate ist der Flash-Agent (firmware/flash_agent/variants.py,
// `matches`): er verweigert jeden echten Mismatch VOR dem Flashen anhand der
// vollen `chip_description` + Whitelist. Dieser Helper steuert nur die UI-Warnung
// und das Deaktivieren des Flash-Buttons, damit der Nutzer
//   (a) nicht durch einen Falsch-Positiv blockiert wird und
//   (b) bei einem offensichtlichen Mismatch gewarnt wird.
//
// Bewusste Designentscheidung: Abgleich auf FAMILIEN-Ebene (tolerant), nicht der
// strikte Whitelist-/Praefix-Vergleich des Agents. Das deckt beide Artefakt-Quellen
// ab — Upload-UI (`expectedChip` grob, z. B. "ESP32") und CLI-Registrierung
// (`expectedChip` voll, z. B. "ESP32-D0WD-V3") — und vermeidet eine sproede
// Duplizierung der Agent-Logik im Frontend.

export type ChipMatchVerdict = 'match' | 'mismatch' | 'unknown';

// Reihenfolge ist relevant: spezifische Varianten VOR dem generischen "esp32",
// da z. B. "ESP32-S3" das Teilwort "esp32" enthaelt.
const FAMILY_RULES: ReadonlyArray<{ pattern: RegExp; family: string }> = [
  { pattern: /esp32-?s3/, family: 'esp32s3' },
  { pattern: /esp32-?s2/, family: 'esp32s2' },
  { pattern: /esp32-?c6/, family: 'esp32c6' },
  { pattern: /esp32-?c3/, family: 'esp32c3' },
  { pattern: /esp32-?h2/, family: 'esp32h2' },
  { pattern: /esp32/, family: 'esp32' }, // klassisch / D0WD / WROOM-32
  { pattern: /esp8266/, family: 'esp8266' },
];

/**
 * Leitet die Chip-Familie aus einer beliebigen Chip-Bezeichnung ab.
 * Akzeptiert sowohl Familien-Strings ("ESP32", bereits vom Agent berechnet)
 * als auch volle Bezeichnungen ("ESP32-D0WD-V3"). Gibt `null` zurueck, wenn
 * keine bekannte Familie erkannt wird.
 */
export function chipFamily(chip: string | null | undefined): string | null {
  if (!chip) return null;
  const norm = chip.toLowerCase().replace(/\s+/g, '');
  for (const { pattern, family } of FAMILY_RULES) {
    if (pattern.test(norm)) return family;
  }
  return null;
}

/**
 * Bewertet, ob ein Artefakt (`expectedChip`) zum erkannten Geraet passt.
 * Geraeteseite bevorzugt das vom Agent gelieferte Familienfeld `device.chip`,
 * faellt sonst auf die volle `device.chipDescription` zurueck.
 *
 * - 'match'    – beide Familien bekannt und gleich.
 * - 'mismatch' – beide Familien bekannt und verschieden (Flash blockieren + warnen).
 * - 'unknown'  – mindestens eine Seite nicht klassifizierbar (nicht blockieren;
 *                der Agent ist das harte Gate).
 */
export function evaluateChipMatch(
  expectedChip: string | null | undefined,
  device: { chip?: string | null; chipDescription?: string | null },
): ChipMatchVerdict {
  const expectedFamily = chipFamily(expectedChip);
  const deviceFamily = chipFamily(device.chip) ?? chipFamily(device.chipDescription);
  if (expectedFamily === null || deviceFamily === null) return 'unknown';
  return expectedFamily === deviceFamily ? 'match' : 'mismatch';
}

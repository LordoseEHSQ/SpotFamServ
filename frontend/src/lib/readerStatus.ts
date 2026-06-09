export type ReaderStatusVariant = 'success' | 'warning' | 'muted';

export interface ReaderStatusBadge {
  text: string;
  variant: ReaderStatusVariant;
}

/**
 * Berechnet Badge-Text und -Variante aus has_api_key + minutes_since_seen.
 * Reine Funktion – ohne React-Abhängigkeit, direkt testbar.
 */
export function readerStatusBadge(
  hasApiKey: boolean,
  minutesSinceSeen: number | null,
): ReaderStatusBadge {
  if (!hasApiKey) {
    return { text: 'Legacy-Reader', variant: 'muted' };
  }
  if (minutesSinceSeen === null) {
    return { text: 'Nie gesehen', variant: 'muted' };
  }
  if (minutesSinceSeen < 5) {
    return { text: 'Gerade aktiv', variant: 'success' };
  }
  if (minutesSinceSeen < 60) {
    return { text: `Vor ${minutesSinceSeen} Min`, variant: 'warning' };
  }
  return { text: `Vor ${Math.floor(minutesSinceSeen / 60)} Std`, variant: 'muted' };
}

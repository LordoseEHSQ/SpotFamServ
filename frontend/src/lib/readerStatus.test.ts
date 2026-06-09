import { describe, it, expect } from 'vitest';
import { readerStatusBadge } from './readerStatus';

describe('readerStatusBadge', () => {
  it('Legacy-Reader: has_api_key=false → grau unabhängig von minutes_since_seen', () => {
    expect(readerStatusBadge(false, null)).toEqual({ text: 'Legacy-Reader', variant: 'muted' });
    expect(readerStatusBadge(false, 0)).toEqual({ text: 'Legacy-Reader', variant: 'muted' });
    expect(readerStatusBadge(false, 120)).toEqual({ text: 'Legacy-Reader', variant: 'muted' });
  });

  it('Nie gesehen: has_api_key=true, minutes_since_seen=null → grau', () => {
    expect(readerStatusBadge(true, null)).toEqual({ text: 'Nie gesehen', variant: 'muted' });
  });

  it('Gerade aktiv: minutes_since_seen < 5 → grün', () => {
    expect(readerStatusBadge(true, 0)).toEqual({ text: 'Gerade aktiv', variant: 'success' });
    expect(readerStatusBadge(true, 4)).toEqual({ text: 'Gerade aktiv', variant: 'success' });
  });

  it('Vor X Min: 5 ≤ minutes_since_seen < 60 → gelb', () => {
    expect(readerStatusBadge(true, 5)).toEqual({ text: 'Vor 5 Min', variant: 'warning' });
    expect(readerStatusBadge(true, 30)).toEqual({ text: 'Vor 30 Min', variant: 'warning' });
    expect(readerStatusBadge(true, 59)).toEqual({ text: 'Vor 59 Min', variant: 'warning' });
  });

  it('Vor X Std: minutes_since_seen ≥ 60 → grau', () => {
    expect(readerStatusBadge(true, 60)).toEqual({ text: 'Vor 1 Std', variant: 'muted' });
    expect(readerStatusBadge(true, 120)).toEqual({ text: 'Vor 2 Std', variant: 'muted' });
    expect(readerStatusBadge(true, 125)).toEqual({ text: 'Vor 2 Std', variant: 'muted' });
  });
});

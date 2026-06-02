import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { scanApi, type ScanEventDto } from '../api/endpoints/scan';
import { useProfiles } from '../hooks/useProfiles';

const OUTCOME_LABELS: Record<string, string> = {
  success: 'Erfolg',
  unknown_card: 'Unbekannte Karte',
  no_binding: 'Keine Playlist-Bindung',
  no_device: 'Kein Gerät',
  token_invalid: 'Token ungültig',
  playback_failed: 'Playback fehlgeschlagen',
  debounced: 'Debounced',
  invalid_request: 'Ungültige Anfrage',
  unknown_reader: 'Unbekannter Reader',
};

function formatOutcome(outcome: string): string {
  return OUTCOME_LABELS[outcome] ?? outcome;
}

function formatDate(iso: string): string {
  try {
    const d = new Date(iso);
    return d.toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'medium' });
  } catch {
    return iso;
  }
}

export function ScanLogsPage() {
  const [profileId, setProfileId] = useState<string>('');
  const [page, setPage] = useState(0);
  const limit = 50;

  const { data: profiles } = useProfiles();

  const { data, isLoading, error } = useQuery({
    queryKey: ['scan-events', profileId || null, page],
    queryFn: () => scanApi.listEvents({ profile_id: profileId || undefined, limit, offset: page * limit }),
    // Live-Tail: neue Scans (auch vom Pi-/ESP-Leser) erscheinen ohne manuelles Neuladen.
    refetchInterval: 3000,
    refetchOnWindowFocus: true,
  });

  const items: ScanEventDto[] = data?.items ?? [];
  const hasMore = items.length === limit;

  return (
    <div>
      <h1 style={{ marginBottom: '0.5rem' }}>Scan-Logs</h1>
      <p style={{ color: '#6b7280', marginBottom: '1rem' }}>
        Alle Scan-Events von RFID-Readern (Erfolg und Fehler).
      </p>

      <div style={{ marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <label style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
          Profil:
          <select
            value={profileId}
            onChange={(e) => { setProfileId(e.target.value); setPage(0); }}
            style={{ padding: '0.5rem 0.75rem', border: '1px solid #d1d5db', borderRadius: 4 }}
          >
            <option value="">Alle</option>
            {(profiles?.items ?? []).map((p) => (
              <option key={p.id} value={p.id}>{p.name}</option>
            ))}
          </select>
        </label>
      </div>

      {isLoading && <p>Lade Events…</p>}
      {error && <p style={{ color: '#dc2626' }}>Scan-Logs konnten nicht geladen werden.</p>}

      {!isLoading && !error && (
        <>
          {items.length === 0 ? (
            <p style={{ color: '#6b7280' }}>Noch keine Scan-Events.</p>
          ) : (
            <>
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 14 }}>
                <thead>
                  <tr style={{ borderBottom: '2px solid #e5e7eb', textAlign: 'left' }}>
                    <th style={{ padding: '0.5rem 0.75rem' }}>Zeit</th>
                    <th style={{ padding: '0.5rem 0.75rem' }}>Card UID</th>
                    <th style={{ padding: '0.5rem 0.75rem' }}>Outcome</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((e) => (
                    <tr key={e.id} style={{ borderBottom: '1px solid #e5e7eb' }}>
                      <td style={{ padding: '0.5rem 0.75rem', whiteSpace: 'nowrap' }}>{formatDate(e.created_at)}</td>
                      <td style={{ padding: '0.5rem 0.75rem', fontFamily: 'monospace' }}>{e.card_uid_raw}</td>
                      <td style={{ padding: '0.5rem 0.75rem' }}>{formatOutcome(e.outcome)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <div style={{ marginTop: '1rem', display: 'flex', gap: 8, alignItems: 'center' }}>
                <button
                  type="button"
                  onClick={() => setPage((p) => Math.max(0, p - 1))}
                  disabled={page === 0}
                  style={{ padding: '0.5rem 0.75rem', border: '1px solid #d1d5db', borderRadius: 4, background: '#fff', cursor: page === 0 ? 'not-allowed' : 'pointer' }}
                >
                  Zurück
                </button>
                <span style={{ color: '#6b7280' }}>Seite {page + 1}</span>
                <button
                  type="button"
                  onClick={() => setPage((p) => p + 1)}
                  disabled={!hasMore}
                  style={{ padding: '0.5rem 0.75rem', border: '1px solid #d1d5db', borderRadius: 4, background: '#fff', cursor: !hasMore ? 'not-allowed' : 'pointer' }}
                >
                  Weiter
                </button>
              </div>
            </>
          )}
        </>
      )}
    </div>
  );
}

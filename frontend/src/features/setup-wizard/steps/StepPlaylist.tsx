import { useState } from 'react';
import type { WizardStepState } from '../../../api/endpoints/setup';
import type { SpotifyPlaylistDto } from '../../../api/endpoints/spotify';

interface StepPlaylistProps {
  stepState: WizardStepState | undefined;
  playlists: SpotifyPlaylistDto[];
  isLoadingPlaylists: boolean;
  onSubmit: (payload: { playlist_uri: string; playlist_id: string; playlist_name: string }) => void;
  onSkip: () => void;
  isSubmitting: boolean;
  error: string | null;
}

export function StepPlaylist({
  stepState,
  playlists,
  isLoadingPlaylists,
  onSubmit,
  onSkip,
  isSubmitting,
  error,
}: StepPlaylistProps) {
  const completed = stepState?.status === 'completed';
  const [selected, setSelected] = useState('');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const pl = playlists.find((p) => p.uri === selected || p.id === selected);
    if (pl) onSubmit({ playlist_uri: pl.uri, playlist_id: pl.id, playlist_name: pl.name });
  };

  return (
    <div>
      <h2 style={{ marginBottom: '0.5rem' }}>Playlist wählen</h2>
      <p style={{ color: '#6b7280', marginBottom: '1rem', fontSize: 14 }}>
        Wählen Sie eine Playlist für die spätere RFID-Bindung (oder überspringen).
      </p>
      {error && (
        <p style={{ color: '#dc2626', marginBottom: '1rem', fontSize: 14 }} role="alert">
          {error}
        </p>
      )}
      {completed ? (
        <p style={{ color: '#059669' }}>Playlist ausgewählt.</p>
      ) : (
        <>
          {isLoadingPlaylists ? (
            <p>Lade Playlists…</p>
          ) : (
            <form onSubmit={handleSubmit}>
              <div style={{ marginBottom: '1rem', maxHeight: 240, overflow: 'auto' }}>
                {playlists.map((p) => (
                  <label key={p.id} style={{ display: 'block', marginBottom: 6 }}>
                    <input
                      type="radio"
                      name="playlist"
                      value={p.uri}
                      checked={selected === p.uri}
                      onChange={() => setSelected(p.uri)}
                      disabled={isSubmitting}
                    />
                    <span style={{ marginLeft: 8 }}>{p.name}</span>
                  </label>
                ))}
              </div>
              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <button type="submit" disabled={isSubmitting || !selected} style={{ padding: '0.5rem 1rem', borderRadius: 6, background: '#2563eb', color: '#fff', border: 0 }}>
                  {isSubmitting ? '…' : 'Weiter'}
                </button>
                <button type="button" onClick={onSkip} disabled={isSubmitting} style={{ padding: '0.5rem 1rem', borderRadius: 6, border: '1px solid #d1d5db', background: '#fff' }}>
                  Überspringen
                </button>
              </div>
            </form>
          )}
        </>
      )}
    </div>
  );
}

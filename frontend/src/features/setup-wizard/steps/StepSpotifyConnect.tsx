import { useEffect } from 'react';
import type { WizardStepState } from '../../../api/endpoints/setup';

interface StepSpotifyConnectProps {
  stepState: WizardStepState | undefined;
  spotifyStatus: string | null;
  authUrl: string | null;
  isLoadingAuthUrl: boolean;
  onFetchAuthUrl: () => void;
  onMarkCompleted: () => void;
  isSubmitting: boolean;
}

export function StepSpotifyConnect({
  stepState,
  spotifyStatus,
  authUrl,
  isLoadingAuthUrl,
  onFetchAuthUrl,
  onMarkCompleted,
  isSubmitting,
}: StepSpotifyConnectProps) {
  useEffect(() => {
    if (spotifyStatus === 'connected') onMarkCompleted();
  }, [spotifyStatus, onMarkCompleted]);

  const completed = stepState?.status === 'completed' || spotifyStatus === 'connected';

  return (
    <div>
      <h2 style={{ marginBottom: '0.5rem' }}>Spotify verbinden</h2>
      <p style={{ color: '#6b7280', marginBottom: '1rem', fontSize: 14 }}>
        Mit dem Spotify-Konto dieses Profils verbinden. Sie werden zu Spotify weitergeleitet.
      </p>
      {completed ? (
        <p style={{ color: '#059669' }}>Spotify ist verbunden.</p>
      ) : (
        <>
          <button
            type="button"
            onClick={onFetchAuthUrl}
            disabled={isLoadingAuthUrl || isSubmitting}
            style={{ padding: '0.5rem 1rem', borderRadius: 6, background: '#2563eb', color: '#fff', border: 0 }}
          >
            {isLoadingAuthUrl ? 'Lade…' : 'Mit Spotify verbinden'}
          </button>
          {authUrl && (
            <p style={{ marginTop: '0.75rem', fontSize: 14 }}>
              <a href={authUrl} target="_blank" rel="noopener noreferrer">
                Falls keine Weiterleitung erfolgt: Hier klicken
              </a>
            </p>
          )}
        </>
      )}
    </div>
  );
}

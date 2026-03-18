import type { WizardStepState } from '../../../api/endpoints/setup';

interface StepSpotifyValidateProps {
  stepState: WizardStepState | undefined;
  onValidate: () => void;
  isSubmitting: boolean;
  error: string | null;
}

export function StepSpotifyValidate({ stepState, onValidate, isSubmitting, error }: StepSpotifyValidateProps) {
  const completed = stepState?.status === 'completed';

  return (
    <div>
      <h2 style={{ marginBottom: '0.5rem' }}>Verbindung prüfen</h2>
      <p style={{ color: '#6b7280', marginBottom: '1rem', fontSize: 14 }}>
        Prüfen, ob die Spotify-Verbindung gültig ist.
      </p>
      {error && (
        <p style={{ color: '#dc2626', marginBottom: '1rem', fontSize: 14 }} role="alert">
          {error}
        </p>
      )}
      {completed ? (
        <p style={{ color: '#059669' }}>Verbindung ist gültig.</p>
      ) : (
        <button
          type="button"
          onClick={onValidate}
          disabled={isSubmitting}
          style={{ padding: '0.5rem 1rem', borderRadius: 6, background: '#2563eb', color: '#fff', border: 0 }}
        >
          {isSubmitting ? 'Prüfe…' : 'Jetzt prüfen'}
        </button>
      )}
    </div>
  );
}

import type { WizardStepState } from '../../../api/endpoints/setup';

interface StepPlaybackTestProps {
  stepState: WizardStepState | undefined;
  onTest: () => void;
  isSubmitting: boolean;
  error: string | null;
}

export function StepPlaybackTest({ stepState, onTest, isSubmitting, error }: StepPlaybackTestProps) {
  const completed = stepState?.status === 'completed';

  return (
    <div>
      <h2 style={{ marginBottom: '0.5rem' }}>Testwiedergabe</h2>
      <p style={{ color: '#6b7280', marginBottom: '1rem', fontSize: 14 }}>
        Starten Sie eine Testwiedergabe auf dem gewählten Lautsprecher.
      </p>
      {error && (
        <p style={{ color: '#dc2626', marginBottom: '1rem', fontSize: 14 }} role="alert">
          {error}
        </p>
      )}
      {completed ? (
        <p style={{ color: '#059669' }}>Test erfolgreich.</p>
      ) : (
        <button
          type="button"
          onClick={onTest}
          disabled={isSubmitting}
          style={{ padding: '0.5rem 1rem', borderRadius: 6, background: '#2563eb', color: '#fff', border: 0 }}
        >
          {isSubmitting ? 'Starte…' : 'Test starten'}
        </button>
      )}
    </div>
  );
}

import type { WizardStepState } from '../../../api/endpoints/setup';
import { STEP_LABELS } from '../stepLabels';

interface StepSummaryProps {
  steps: WizardStepState[];
  profileName: string;
  onComplete: () => void;
  isSubmitting: boolean;
  error: string | null;
}

export function StepSummary({ steps, profileName, onComplete, isSubmitting, error }: StepSummaryProps) {
  const allCompleted = steps.filter((s) => s.step_key !== 'summary').every((s) => s.status === 'completed');

  return (
    <div>
      <h2 style={{ marginBottom: '0.5rem' }}>Zusammenfassung</h2>
      <p style={{ color: '#6b7280', marginBottom: '1rem', fontSize: 14 }}>
        Setup für <strong>{profileName}</strong>. Alle Schritte abgeschlossen?
      </p>
      {error && (
        <p style={{ color: '#dc2626', marginBottom: '1rem', fontSize: 14 }} role="alert">
          {error}
        </p>
      )}
      <ul style={{ marginBottom: '1.5rem', paddingLeft: '1.25rem' }}>
        {steps
          .filter((s) => s.step_key !== 'summary')
          .map((s) => (
            <li key={s.step_key} style={{ marginBottom: 4 }}>
              {STEP_LABELS[s.step_key] ?? s.step_key}: {s.status === 'completed' ? '✓' : s.status === 'failed' ? '✗' : '–'}
            </li>
          ))}
      </ul>
      <button
        type="button"
        onClick={onComplete}
        disabled={isSubmitting || !allCompleted}
        style={{ padding: '0.5rem 1rem', borderRadius: 6, background: '#059669', color: '#fff', border: 0 }}
      >
        {isSubmitting ? 'Speichern…' : 'Setup abschließen'}
      </button>
    </div>
  );
}

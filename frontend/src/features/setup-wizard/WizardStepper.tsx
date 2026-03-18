import { WIZARD_STEPS, type WizardStepState } from '../../api/endpoints/setup';
import { getStepStatus, isStepAccessible } from './useSetupWizard';
import { STEP_LABELS } from './stepLabels';

interface WizardStepperProps {
  steps: WizardStepState[];
  currentStep: string;
  onStepClick: (stepKey: string) => void;
}

export function WizardStepper({ steps, currentStep, onStepClick }: WizardStepperProps) {
  return (
    <nav aria-label="Setup-Fortschritt" style={{ marginBottom: '1.5rem' }}>
      <ol style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
        {WIZARD_STEPS.map((stepKey: string, idx: number) => {
          const status = getStepStatus(steps, stepKey);
          const accessible = isStepAccessible(steps, currentStep, stepKey);
          const isCurrent = currentStep === stepKey;
          const label = STEP_LABELS[stepKey] ?? stepKey;
          return (
            <li key={stepKey}>
              <button
                type="button"
                onClick={() => accessible && onStepClick(stepKey)}
                disabled={!accessible}
                aria-current={isCurrent ? 'step' : undefined}
                aria-label={`${label}: ${status}`}
                style={{
                  padding: '0.4rem 0.75rem',
                  borderRadius: 6,
                  border: isCurrent ? '2px solid #2563eb' : '1px solid #e5e7eb',
                  background: isCurrent ? '#eff6ff' : accessible ? '#fff' : '#f3f4f6',
                  color: accessible ? '#1f2937' : '#9ca3af',
                  cursor: accessible ? 'pointer' : 'default',
                  fontSize: 14,
                }}
              >
                <span style={{ marginRight: 6 }}>{idx + 1}.</span>
                {label}
                {status === 'completed' && ' ✓'}
                {status === 'failed' && ' ✗'}
              </button>
            </li>
          );
        })}
      </ol>
    </nav>
  );
}

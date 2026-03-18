import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { setupApi, WIZARD_STEPS, type WizardStepState, type WizardStepKey } from '../../api/endpoints/setup';

export const setupWizardKeys = {
  all: (profileId: string) => ['setup', profileId] as const,
  state: (profileId: string) => [...setupWizardKeys.all(profileId), 'state'] as const,
  completeness: (profileId: string) => [...setupWizardKeys.all(profileId), 'completeness'] as const,
};

export function useSetupWizardState(profileId: string | undefined | null) {
  return useQuery({
    queryKey: setupWizardKeys.state(profileId ?? ''),
    queryFn: () => setupApi.getState(profileId!),
    enabled: !!profileId,
  });
}

export function useSetupCompleteness(profileId: string | undefined | null) {
  return useQuery({
    queryKey: setupWizardKeys.completeness(profileId ?? ''),
    queryFn: () => setupApi.getCompleteness(profileId!),
    enabled: !!profileId,
  });
}

export function useSubmitStep(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: { step_key: string; status: string; payload?: Record<string, unknown> | null }) =>
      setupApi.submitStep(profileId, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: setupWizardKeys.all(profileId) });
    },
  });
}

export function useSetCurrentStep(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (currentStep: string) => setupApi.setCurrentStep(profileId, currentStep),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: setupWizardKeys.all(profileId) });
    },
  });
}

export function getStepStatus(steps: WizardStepState[], stepKey: string): string {
  return steps.find((s) => s.step_key === stepKey)?.status ?? 'pending';
}

export function isStepAccessible(_steps: WizardStepState[], currentStep: string, stepKey: string): boolean {
  const order = WIZARD_STEPS;
  const currentIdx = order.indexOf(currentStep as WizardStepKey);
  const stepIdx = order.indexOf(stepKey as WizardStepKey);
  if (stepIdx < 0) return false;
  if (stepIdx <= currentIdx) return true;
  return stepIdx === currentIdx + 1;
}

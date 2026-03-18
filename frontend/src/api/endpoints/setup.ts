import { api } from '../client';

export interface WizardStepState {
  step_key: string;
  status: string;
  payload?: Record<string, unknown> | null;
}

export interface WizardStateResponse {
  current_step: string;
  status: string;
  session_id: string | null;
  steps: WizardStepState[];
}

export interface CompletenessResponse {
  percent: number;
  session_status: string;
  steps: WizardStepState[];
}

export const WIZARD_STEPS = [
  'profile',
  'spotify_connect',
  'spotify_validate',
  'devices',
  'default_speaker',
  'playback_test',
  'playlist',
  'rfid_bind',
  'summary',
] as const;

export type WizardStepKey = (typeof WIZARD_STEPS)[number];

export const setupApi = {
  getState: (profileId: string) =>
    api.get<WizardStateResponse>(`/profiles/${profileId}/setup`),
  submitStep: (profileId: string, data: { step_key: string; status: string; payload?: Record<string, unknown> | null }) =>
    api.put<WizardStateResponse>(`/profiles/${profileId}/setup/step`, data),
  setCurrentStep: (profileId: string, currentStep: string) =>
    api.put<WizardStateResponse>(`/profiles/${profileId}/setup/current-step`, { current_step: currentStep }),
  getCompleteness: (profileId: string) =>
    api.get<CompletenessResponse>(`/profiles/${profileId}/setup/completeness`),
};

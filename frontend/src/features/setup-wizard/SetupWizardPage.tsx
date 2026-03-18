import { useParams, Link } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { useSetupWizardState, useSubmitStep, useSetCurrentStep } from './useSetupWizard';
import { WIZARD_STEPS } from '../../api/endpoints/setup';
import { spotifyApi } from '../../api/endpoints/spotify';
import { rfidApi } from '../../api/endpoints/rfid';
import { useQuery } from '@tanstack/react-query';
import { useProfile } from '../../hooks/useProfiles';
import { WizardStepper } from './WizardStepper';
import { StepProfile } from './steps/StepProfile';
import { StepSpotifyConnect } from './steps/StepSpotifyConnect';
import { StepSpotifyValidate } from './steps/StepSpotifyValidate';
import { StepDevices } from './steps/StepDevices';
import { StepDefaultSpeaker } from './steps/StepDefaultSpeaker';
import { StepPlaybackTest } from './steps/StepPlaybackTest';
import { StepPlaylist } from './steps/StepPlaylist';
import { StepRfidBind } from './steps/StepRfidBind';
import { StepSummary } from './steps/StepSummary';

export function SetupWizardPage() {
  const { profileId } = useParams<{ profileId: string }>();
  const { data: state, isLoading: stateLoading, error: stateError } = useSetupWizardState(profileId);
  const { data: profile } = useProfile(profileId);
  const submitStep = useSubmitStep(profileId!);
  const setCurrentStep = useSetCurrentStep(profileId!);

  const spotifyStatus = useQuery({
    queryKey: ['spotify', 'status', profileId],
    queryFn: () => spotifyApi.getStatus(profileId!),
    enabled: !!profileId && (state?.current_step === 'spotify_connect' || state?.current_step === 'spotify_validate'),
  });
  const authUrl = useQuery({
    queryKey: ['spotify', 'authUrl', profileId],
    queryFn: () => spotifyApi.getAuthorizationUrl(profileId!),
    enabled: false,
  });
  const devices = useQuery({
    queryKey: ['spotify', 'devices', profileId],
    queryFn: () => spotifyApi.getDevices(profileId!),
    enabled: !!profileId && (state?.current_step === 'devices' || state?.current_step === 'default_speaker'),
  });
  const playlists = useQuery({
    queryKey: ['spotify', 'playlists', profileId],
    queryFn: () => spotifyApi.getPlaylists(profileId!),
    enabled: !!profileId && state?.current_step === 'playlist',
  });
  const rfidCards = useQuery({
    queryKey: ['rfid-cards', profileId],
    queryFn: () => rfidApi.list(profileId!),
    enabled: !!profileId && state?.current_step === 'rfid_bind',
  });

  const validateMutation = useMutation({
    mutationFn: () => spotifyApi.validate(profileId!),
    onSuccess: () => handleSubmitStep('spotify_validate'),
  });

  const handleStepClick = (stepKey: string) => {
    setCurrentStep.mutate(stepKey);
  };

  const handleSubmitStep = (stepKey: string, payload?: Record<string, unknown> | null) => {
    submitStep.mutate({ step_key: stepKey, status: 'completed', payload }, {
      onError: () => {},
    });
  };

  const handleTestPlayback = () => {
    handleSubmitStep('playback_test', { context_uri: 'spotify:playlist:37i9dQZF1DXcBWIGoYBM5M' });
  };

  if (stateLoading || !profileId) {
    return <p>Lade Setup…</p>;
  }
  if (stateError) {
    return (
      <div>
        <p style={{ color: '#dc2626' }}>Setup konnte nicht geladen werden.</p>
        <Link to={`/profiles/${profileId}`}>← Zurück zum Profil</Link>
      </div>
    );
  }

  const steps = state?.steps ?? [];
  const currentStep = state?.current_step ?? 'profile';
  const stepState = steps.find((s) => s.step_key === currentStep);
  const submitError = (submitStep.error as Error & { body?: { error?: string } })?.body?.error ?? null;
  const validateError = (validateMutation.error as Error)?.message ?? null;

  return (
    <div>
      <div style={{ marginBottom: '1rem' }}>
        <Link to={`/profiles/${profileId}`} style={{ color: '#6b7280', fontSize: 14 }}>
          ← Profil
        </Link>
      </div>
      <h1 style={{ marginBottom: '0.5rem' }}>Setup-Wizard</h1>
      <p style={{ color: '#6b7280', marginBottom: '1rem', fontSize: 14 }}>
        Fortschritt: {steps.filter((s) => s.status === 'completed').length} / {WIZARD_STEPS.length - 1} Schritte
      </p>

      <WizardStepper steps={steps} currentStep={currentStep} onStepClick={handleStepClick} />

      <section aria-label={`Schritt: ${currentStep}`} style={{ background: '#fff', padding: '1.5rem', borderRadius: 8, border: '1px solid #e5e7eb' }}>
        {currentStep === 'profile' && (
          <StepProfile
            profileName={profile?.name ?? ''}
            profileDescription={profile?.description ?? null}
            onSubmit={(p) => handleSubmitStep('profile', p)}
            isSubmitting={submitStep.isPending}
            error={submitError}
          />
        )}
        {currentStep === 'spotify_connect' && (
          <StepSpotifyConnect
            stepState={stepState}
            spotifyStatus={spotifyStatus.data?.status ?? null}
            authUrl={authUrl.data?.authorization_url ?? null}
            isLoadingAuthUrl={authUrl.isFetching}
            onFetchAuthUrl={() => authUrl.refetch().then((r) => r.data?.authorization_url && (window.location.href = r.data.authorization_url))}
            onMarkCompleted={() => handleSubmitStep('spotify_connect')}
            isSubmitting={submitStep.isPending}
          />
        )}
        {currentStep === 'spotify_validate' && (
          <StepSpotifyValidate
            stepState={stepState}
            onValidate={() => validateMutation.mutate()}
            isSubmitting={validateMutation.isPending || submitStep.isPending}
            error={validateError ?? submitError}
          />
        )}
        {currentStep === 'devices' && (
          <StepDevices
            stepState={stepState}
            devices={devices.data?.items ?? []}
            isLoadingDevices={devices.isLoading}
            onMarkCompleted={() => handleSubmitStep('devices')}
            isSubmitting={submitStep.isPending}
          />
        )}
        {currentStep === 'default_speaker' && (
          <StepDefaultSpeaker
            stepState={stepState}
            devices={devices.data?.items ?? []}
            defaultDeviceId={profile?.default_spotify_device_id ?? null}
            isLoadingDevices={devices.isLoading}
            onSubmit={(deviceId) => handleSubmitStep('default_speaker', { device_id: deviceId })}
            isSubmitting={submitStep.isPending}
            error={submitError}
          />
        )}
        {currentStep === 'playback_test' && (
          <StepPlaybackTest
            stepState={stepState}
            onTest={handleTestPlayback}
            isSubmitting={submitStep.isPending}
            error={submitError}
          />
        )}
        {currentStep === 'playlist' && (
          <StepPlaylist
            stepState={stepState}
            playlists={playlists.data?.items ?? []}
            isLoadingPlaylists={playlists.isLoading}
            onSubmit={(p) => handleSubmitStep('playlist', p)}
            onSkip={() => handleSubmitStep('playlist', { skip: true })}
            isSubmitting={submitStep.isPending}
            error={submitError}
          />
        )}
        {currentStep === 'rfid_bind' && (
          <StepRfidBind
            stepState={stepState}
            cards={rfidCards.data?.items ?? []}
            isLoadingCards={rfidCards.isLoading}
            onMarkCompleted={() => handleSubmitStep('rfid_bind')}
            onSkip={() => handleSubmitStep('rfid_bind', { skip: true })}
            isSubmitting={submitStep.isPending}
          />
        )}
        {currentStep === 'summary' && (
          <StepSummary
            steps={steps}
            profileName={profile?.name ?? ''}
            onComplete={() => handleSubmitStep('summary')}
            isSubmitting={submitStep.isPending}
            error={submitError}
          />
        )}
      </section>
    </div>
  );
}

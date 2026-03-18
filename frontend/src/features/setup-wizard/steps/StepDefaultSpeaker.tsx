import { useState } from 'react';
import type { WizardStepState } from '../../../api/endpoints/setup';
import type { SpotifyDeviceDto } from '../../../api/endpoints/spotify';

interface StepDefaultSpeakerProps {
  stepState: WizardStepState | undefined;
  devices: SpotifyDeviceDto[];
  defaultDeviceId: string | null;
  isLoadingDevices: boolean;
  onSubmit: (deviceId: string) => void;
  isSubmitting: boolean;
  error: string | null;
}

export function StepDefaultSpeaker({
  stepState,
  devices,
  defaultDeviceId,
  isLoadingDevices,
  onSubmit,
  isSubmitting,
  error,
}: StepDefaultSpeakerProps) {
  const completed = stepState?.status === 'completed';
  const [selected, setSelected] = useState(defaultDeviceId ?? '');

  return (
    <div>
      <h2 style={{ marginBottom: '0.5rem' }}>Standard-Lautsprecher</h2>
      <p style={{ color: '#6b7280', marginBottom: '1rem', fontSize: 14 }}>
        Wählen Sie das Gerät, auf dem die Playback beim RFID-Scan starten soll.
      </p>
      {error && (
        <p style={{ color: '#dc2626', marginBottom: '1rem', fontSize: 14 }} role="alert">
          {error}
        </p>
      )}
      {completed ? (
        <p style={{ color: '#059669' }}>Standard-Lautsprecher ist gesetzt.</p>
      ) : (
        <>
          {isLoadingDevices ? (
            <p>Lade Geräte…</p>
          ) : (
            <form
              onSubmit={(e) => {
                e.preventDefault();
                if (selected) onSubmit(selected);
              }}
            >
              <div style={{ marginBottom: '1rem' }}>
                {devices.map((d) => (
                  <label key={d.id} style={{ display: 'block', marginBottom: 8 }}>
                    <input
                      type="radio"
                      name="device"
                      value={d.id}
                      checked={selected === d.id}
                      onChange={() => setSelected(d.id)}
                      disabled={isSubmitting}
                    />
                    <span style={{ marginLeft: 8 }}>{d.name} ({d.type})</span>
                  </label>
                ))}
              </div>
              <button type="submit" disabled={isSubmitting || !selected} style={{ padding: '0.5rem 1rem', borderRadius: 6, background: '#2563eb', color: '#fff', border: 0 }}>
                {isSubmitting ? 'Speichern…' : 'Weiter'}
              </button>
            </form>
          )}
        </>
      )}
    </div>
  );
}

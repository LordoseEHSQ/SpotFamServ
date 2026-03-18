import type { WizardStepState } from '../../../api/endpoints/setup';
import type { SpotifyDeviceDto } from '../../../api/endpoints/spotify';

interface StepDevicesProps {
  stepState: WizardStepState | undefined;
  devices: SpotifyDeviceDto[];
  isLoadingDevices: boolean;
  onMarkCompleted: () => void;
  isSubmitting: boolean;
}

export function StepDevices({
  stepState,
  devices,
  isLoadingDevices,
  onMarkCompleted,
  isSubmitting,
}: StepDevicesProps) {
  const completed = stepState?.status === 'completed';

  return (
    <div>
      <h2 style={{ marginBottom: '0.5rem' }}>Geräte</h2>
      <p style={{ color: '#6b7280', marginBottom: '1rem', fontSize: 14 }}>
        Verfügbare Spotify-Geräte. Im nächsten Schritt wählen Sie den Standard-Lautsprecher.
      </p>
      {completed ? (
        <p style={{ color: '#059669' }}>Schritt abgeschlossen.</p>
      ) : (
        <>
          {isLoadingDevices ? (
            <p>Lade Geräte…</p>
          ) : devices.length === 0 ? (
            <p style={{ color: '#d97706' }}>Keine Geräte gefunden. Bitte Spotify auf einem Gerät starten.</p>
          ) : (
            <ul style={{ listStyle: 'none', padding: 0 }}>
              {devices.map((d) => (
                <li key={d.id} style={{ padding: '0.25rem 0' }}>
                  {d.name} <span style={{ color: '#6b7280' }}>({d.type})</span>
                  {d.is_active && <span style={{ marginLeft: 8, color: '#059669' }}>aktiv</span>}
                </li>
              ))}
            </ul>
          )}
          <button
            type="button"
            onClick={onMarkCompleted}
            disabled={isSubmitting || isLoadingDevices}
            style={{ marginTop: '1rem', padding: '0.5rem 1rem', borderRadius: 6, background: '#2563eb', color: '#fff', border: 0 }}
          >
            {isSubmitting ? '…' : 'Weiter'}
          </button>
        </>
      )}
    </div>
  );
}

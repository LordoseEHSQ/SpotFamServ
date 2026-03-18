import type { WizardStepState } from '../../../api/endpoints/setup';
import type { RfidCardDto } from '../../../api/endpoints/rfid';

interface StepRfidBindProps {
  stepState: WizardStepState | undefined;
  cards: RfidCardDto[];
  isLoadingCards: boolean;
  onMarkCompleted: () => void;
  onSkip: () => void;
  isSubmitting: boolean;
}

export function StepRfidBind({
  stepState,
  cards,
  isLoadingCards,
  onMarkCompleted,
  onSkip,
  isSubmitting,
}: StepRfidBindProps) {
  const completed = stepState?.status === 'completed';

  return (
    <div>
      <h2 style={{ marginBottom: '0.5rem' }}>RFID-Karte binden</h2>
      <p style={{ color: '#6b7280', marginBottom: '1rem', fontSize: 14 }}>
        Optional: Erste RFID-Karte an die gewählte Playlist binden. Sie können das später unter „RFID-Karten“ nachholen.
      </p>
      {completed ? (
        <p style={{ color: '#059669' }}>Schritt abgeschlossen.</p>
      ) : (
        <>
          {isLoadingCards && <p>Lade Karten…</p>}
          {!isLoadingCards && cards.length > 0 && (
            <p style={{ marginBottom: '1rem' }}>Vorhandene Karten: {cards.map((c) => c.card_uid).join(', ')}</p>
          )}
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button
              type="button"
              onClick={onMarkCompleted}
              disabled={isSubmitting}
              style={{ padding: '0.5rem 1rem', borderRadius: 6, background: '#2563eb', color: '#fff', border: 0 }}
            >
              {isSubmitting ? '…' : 'Weiter'}
            </button>
            <button type="button" onClick={onSkip} disabled={isSubmitting} style={{ padding: '0.5rem 1rem', borderRadius: 6, border: '1px solid #d1d5db', background: '#fff' }}>
              Überspringen
            </button>
          </div>
        </>
      )}
    </div>
  );
}

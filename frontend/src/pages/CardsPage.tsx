import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import type { RfidCardDto, CardPlaylistBindingDto } from '../api/endpoints/rfid';
import {
  useRfidCards,
  useCardBinding,
  usePlaylistReferences,
  useCreateRfidCard,
  useUpdateRfidCard,
  useDeleteRfidCard,
  useSetCardBinding,
} from '../hooks/useRfidCards';

export function CardsPage() {
  const { profileId } = useParams<{ profileId: string }>();
  const [createOpen, setCreateOpen] = useState(false);
  const [createUid, setCreateUid] = useState('');
  const [createLabel, setCreateLabel] = useState('');
  const [editingCard, setEditingCard] = useState<RfidCardDto | null>(null);
  const [editLabel, setEditLabel] = useState('');
  const [bindingCard, setBindingCard] = useState<RfidCardDto | null>(null);
  const [bindingRefId, setBindingRefId] = useState<string>('');

  const { data, isLoading, error } = useRfidCards(profileId);
  const { data: playlistRefs } = usePlaylistReferences(profileId, !!bindingCard);
  const { data: currentBinding } = useCardBinding(profileId, bindingCard?.id);

  const createMutation = useCreateRfidCard(profileId!);
  const updateMutation = useUpdateRfidCard(profileId!);
  const deleteMutation = useDeleteRfidCard(profileId!);
  const setBindingMutation = useSetCardBinding(profileId!);

  const openEdit = (c: RfidCardDto) => {
    setEditingCard(c);
    setEditLabel(c.label ?? '');
  };

  const openBinding = (c: RfidCardDto) => {
    setBindingCard(c);
    setBindingRefId('');
  };

  useEffect(() => {
    if (bindingCard && currentBinding !== undefined) {
      setBindingRefId((currentBinding as CardPlaylistBindingDto)?.id ?? '');
    }
  }, [bindingCard, currentBinding]);

  const handleCreateSuccess = () => {
    setCreateOpen(false);
    setCreateUid('');
    setCreateLabel('');
  };

  const handleUpdateSuccess = () => {
    setEditingCard(null);
    setEditLabel('');
  };

  const handleBindingSuccess = () => {
    setBindingCard(null);
    setBindingRefId('');
  };

  const items = data?.items ?? [];

  if (isLoading) return <p>Lade Karten…</p>;
  if (error) return <p style={{ color: '#dc2626' }}>Karten konnten nicht geladen werden.</p>;

  return (
    <div>
      <div style={{ marginBottom: '1rem' }}>
        <Link to={`/profiles/${profileId}`} style={{ color: '#6b7280', fontSize: 14 }}>← Profil</Link>
      </div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
        <h1 style={{ margin: 0 }}>RFID-Karten</h1>
        <button
          type="button"
          onClick={() => setCreateOpen(true)}
          style={{ padding: '0.5rem 1rem', borderRadius: 6, border: '1px solid #d1d5db', background: '#fff', cursor: 'pointer' }}
        >
          Neue Karte
        </button>
      </div>

      {createOpen && (
        <div style={{ marginBottom: '1.5rem', padding: '1rem', border: '1px solid #e5e7eb', borderRadius: 8 }}>
          <h3 style={{ marginTop: 0 }}>Karte anlegen</h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', maxWidth: 320 }}>
            <label>
              Card UID <span style={{ color: '#dc2626' }}>*</span>
              <input
                value={createUid}
                onChange={(e) => setCreateUid(e.target.value)}
                placeholder="z. B. 04A1B2C3D4E5F6"
                style={{ display: 'block', width: '100%', padding: '0.5rem', marginTop: 2, border: '1px solid #d1d5db', borderRadius: 4 }}
              />
            </label>
            <label>
              Label (optional)
              <input
                value={createLabel}
                onChange={(e) => setCreateLabel(e.target.value)}
                placeholder="z. B. Kinderzimmer"
                style={{ display: 'block', width: '100%', padding: '0.5rem', marginTop: 2, border: '1px solid #d1d5db', borderRadius: 4 }}
              />
            </label>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <button
                type="button"
                onClick={() => createMutation.mutate(
                  { card_uid: createUid.trim(), label: createLabel.trim() || null },
                  { onSuccess: handleCreateSuccess },
                )}
                disabled={!createUid.trim() || createMutation.isPending}
                style={{ padding: '0.5rem 1rem', borderRadius: 6, border: 'none', background: '#2563eb', color: '#fff', cursor: 'pointer' }}
              >
                {createMutation.isPending ? 'Speichern…' : 'Anlegen'}
              </button>
              <button
                type="button"
                onClick={() => { setCreateOpen(false); setCreateUid(''); setCreateLabel(''); }}
                style={{ padding: '0.5rem 1rem', borderRadius: 6, border: '1px solid #d1d5db', background: '#fff', cursor: 'pointer' }}
              >
                Abbrechen
              </button>
            </div>
            {createMutation.isError && (
              <span style={{ color: '#dc2626', fontSize: 14 }}>{(createMutation.error as Error).message}</span>
            )}
          </div>
        </div>
      )}

      {items.length === 0 && !createOpen ? (
        <p style={{ color: '#6b7280' }}>Noch keine Karten für dieses Profil.</p>
      ) : (
        <ul style={{ listStyle: 'none', padding: 0 }}>
          {items.map((c) => (
            <li key={c.id} style={{ padding: '0.75rem 0', borderBottom: '1px solid #e5e7eb', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
              <div>
                <span style={{ fontFamily: 'monospace' }}>{c.card_uid}</span>
                {c.label && <span style={{ color: '#6b7280', marginLeft: 8 }}>– {c.label}</span>}
              </div>
              <div style={{ display: 'flex', gap: 8 }}>
                <button
                  type="button"
                  onClick={() => openBinding(c)}
                  style={{ padding: '0.25rem 0.5rem', fontSize: 13, border: '1px solid #d1d5db', borderRadius: 4, background: '#fff', cursor: 'pointer' }}
                >
                  Playlist
                </button>
                <button
                  type="button"
                  onClick={() => openEdit(c)}
                  style={{ padding: '0.25rem 0.5rem', fontSize: 13, border: '1px solid #d1d5db', borderRadius: 4, background: '#fff', cursor: 'pointer' }}
                >
                  Bearbeiten
                </button>
                <button
                  type="button"
                  onClick={() => window.confirm('Karte wirklich löschen?') && deleteMutation.mutate(c.id)}
                  disabled={deleteMutation.isPending}
                  style={{ padding: '0.25rem 0.5rem', fontSize: 13, border: '1px solid #dc2626', color: '#dc2626', borderRadius: 4, background: '#fff', cursor: 'pointer' }}
                >
                  Löschen
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {editingCard && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.3)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 10 }}>
          <div style={{ background: '#fff', padding: '1.5rem', borderRadius: 8, minWidth: 320 }}>
            <h3 style={{ marginTop: 0 }}>Karte bearbeiten</h3>
            <label style={{ display: 'block', marginBottom: 8 }}>
              Label
              <input
                value={editLabel}
                onChange={(e) => setEditLabel(e.target.value)}
                style={{ display: 'block', width: '100%', padding: '0.5rem', marginTop: 2, border: '1px solid #d1d5db', borderRadius: 4 }}
              />
            </label>
            <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
              <button
                type="button"
                onClick={() => updateMutation.mutate(
                  { cardId: editingCard.id, label: editLabel.trim() || null },
                  { onSuccess: handleUpdateSuccess },
                )}
                disabled={updateMutation.isPending}
                style={{ padding: '0.5rem 1rem', borderRadius: 6, border: 'none', background: '#2563eb', color: '#fff', cursor: 'pointer' }}
              >
                Speichern
              </button>
              <button
                type="button"
                onClick={() => { setEditingCard(null); setEditLabel(''); }}
                style={{ padding: '0.5rem 1rem', borderRadius: 6, border: '1px solid #d1d5db', background: '#fff', cursor: 'pointer' }}
              >
                Abbrechen
              </button>
            </div>
            {updateMutation.isError && (
              <p style={{ color: '#dc2626', fontSize: 14, marginTop: 8 }}>{(updateMutation.error as Error).message}</p>
            )}
          </div>
        </div>
      )}

      {bindingCard && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.3)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 10 }}>
          <div style={{ background: '#fff', padding: '1.5rem', borderRadius: 8, minWidth: 320 }}>
            <h3 style={{ marginTop: 0 }}>Playlist-Bindung: {bindingCard.card_uid}</h3>
            <label style={{ display: 'block', marginBottom: 8 }}>
              Playlist-Referenz
              <select
                value={bindingRefId}
                onChange={(e) => setBindingRefId(e.target.value)}
                style={{ display: 'block', width: '100%', padding: '0.5rem', marginTop: 2, border: '1px solid #d1d5db', borderRadius: 4 }}
              >
                <option value="">— Keine —</option>
                {(playlistRefs?.items ?? []).map((r: { id: string; name: string }) => (
                  <option key={r.id} value={r.id}>{r.name}</option>
                ))}
              </select>
            </label>
            <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
              <button
                type="button"
                onClick={() => setBindingMutation.mutate(
                  { cardId: bindingCard.id, refId: bindingRefId || null },
                  { onSuccess: handleBindingSuccess },
                )}
                disabled={setBindingMutation.isPending}
                style={{ padding: '0.5rem 1rem', borderRadius: 6, border: 'none', background: '#2563eb', color: '#fff', cursor: 'pointer' }}
              >
                Speichern
              </button>
              <button
                type="button"
                onClick={() => { setBindingCard(null); setBindingRefId(''); }}
                style={{ padding: '0.5rem 1rem', borderRadius: 6, border: '1px solid #d1d5db', background: '#fff', cursor: 'pointer' }}
              >
                Abbrechen
              </button>
            </div>
            {setBindingMutation.isError && (
              <p style={{ color: '#dc2626', fontSize: 14, marginTop: 8 }}>{(setBindingMutation.error as Error).message}</p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

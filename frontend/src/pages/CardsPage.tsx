import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import type { RfidCardDto, CardPlaylistBindingDto } from '../api/endpoints/rfid';
import { scanApi } from '../api/endpoints/scan';
import {
  useRfidCards,
  useCardBinding,
  useCardLookup,
  usePlaylistReferences,
  useCreatePlaylistReference,
  useCreateRfidCard,
  useUpdateRfidCard,
  useDeleteRfidCard,
  useSetCardBinding,
} from '../hooks/useRfidCards';
import { useSpotifyPlaylists } from '../hooks/useSpotifyPlaylists';

export function CardsPage() {
  const { profileId } = useParams<{ profileId: string }>();
  const [createOpen, setCreateOpen] = useState(false);
  const [createUid, setCreateUid] = useState('');
  const [createLabel, setCreateLabel] = useState('');
  const [editingCard, setEditingCard] = useState<RfidCardDto | null>(null);
  const [editLabel, setEditLabel] = useState('');
  const [bindingCard, setBindingCard] = useState<RfidCardDto | null>(null);
  const [bindingPlaylistId, setBindingPlaylistId] = useState<string>('');
  const [bindingBusy, setBindingBusy] = useState(false);
  const [bindingError, setBindingError] = useState<string | null>(null);

  // Scan-to-Create: wartet auf einen NEUEN Scan (Baseline = jüngstes Event beim Start),
  // prüft die UID per Lookup gegen alle Profile und zeigt belegt/frei.
  const [scanMode, setScanMode] = useState(false);
  const [scannedUid, setScannedUid] = useState<string | null>(null);
  const [baseline, setBaseline] = useState<{ set: boolean; id: string | null }>({ set: false, id: null });

  const { data, isLoading, error } = useRfidCards(profileId);
  const { data: playlistRefs } = usePlaylistReferences(profileId, !!bindingCard);
  const { data: currentBinding } = useCardBinding(profileId, bindingCard?.id);
  const { data: spotifyPlaylists, isLoading: playlistsLoading, error: playlistsError } =
    useSpotifyPlaylists(profileId!, !!bindingCard);

  const createMutation = useCreateRfidCard(profileId!);
  const updateMutation = useUpdateRfidCard(profileId!);
  const deleteMutation = useDeleteRfidCard(profileId!);
  const setBindingMutation = useSetCardBinding(profileId!);
  const createRefMutation = useCreatePlaylistReference(profileId!);

  const { data: scanEvents } = useQuery({
    queryKey: ['scan-events', 'enroll'],
    queryFn: () => scanApi.listEvents({ limit: 5 }),
    refetchInterval: 2000,
    enabled: scanMode && !scannedUid,
  });

  const { data: lookup, isLoading: lookupLoading } = useCardLookup(scannedUid);

  useEffect(() => {
    if (!scanMode) return;
    const events = scanEvents?.items ?? [];
    const newest = events[0];
    if (!baseline.set) {
      setBaseline({ set: true, id: newest?.id ?? null });
      return;
    }
    if (!scannedUid && newest && newest.id !== baseline.id) {
      setScannedUid(newest.card_uid_raw);
    }
  }, [scanMode, scanEvents, baseline, scannedUid]);

  const startScan = () => {
    setScannedUid(null);
    setBaseline({ set: false, id: null });
    setScanMode(true);
  };

  const stopScan = () => {
    setScanMode(false);
    setScannedUid(null);
    setBaseline({ set: false, id: null });
  };

  const rescan = () => {
    setScannedUid(null);
    setBaseline({ set: false, id: null });
  };

  const adoptScannedUid = () => {
    if (!scannedUid) return;
    setCreateUid(scannedUid);
    setCreateLabel('');
    setCreateOpen(true);
    stopScan();
  };

  const openEdit = (c: RfidCardDto) => {
    setEditingCard(c);
    setEditLabel(c.label ?? '');
  };

  const openBinding = (c: RfidCardDto) => {
    setBindingCard(c);
    setBindingPlaylistId('');
    setBindingError(null);
  };

  useEffect(() => {
    if (bindingCard && currentBinding !== undefined) {
      setBindingPlaylistId((currentBinding as CardPlaylistBindingDto)?.spotify_playlist_id ?? '');
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
    setBindingPlaylistId('');
    setBindingError(null);
  };

  // Bindet die Karte an die gewählte Spotify-Playlist. Legt bei Bedarf zuerst eine
  // Playlist-Referenz an (das Binding referenziert intern eine Referenz-ID, nicht
  // die Spotify-ID direkt), sucht aber eine bestehende Referenz wieder, um Duplikate
  // zu vermeiden.
  const saveBinding = async () => {
    if (!bindingCard) return;
    setBindingError(null);

    if (!bindingPlaylistId) {
      setBindingMutation.mutate({ cardId: bindingCard.id, refId: null }, { onSuccess: handleBindingSuccess });
      return;
    }

    setBindingBusy(true);
    try {
      const existing = (playlistRefs?.items ?? []).find((r) => r.spotify_playlist_id === bindingPlaylistId);
      let refId = existing?.id;
      if (!refId) {
        const pl = (spotifyPlaylists?.items ?? []).find((p) => p.id === bindingPlaylistId);
        const created = await createRefMutation.mutateAsync({
          spotify_playlist_id: bindingPlaylistId,
          name: pl?.name ?? bindingPlaylistId,
          owner_id: pl?.owner_id ?? null,
        });
        refId = created.id;
      }
      setBindingMutation.mutate({ cardId: bindingCard.id, refId }, { onSuccess: handleBindingSuccess });
    } catch (e) {
      setBindingError((e as Error).message);
    } finally {
      setBindingBusy(false);
    }
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
        <div style={{ display: 'flex', gap: 8 }}>
          <button
            type="button"
            onClick={scanMode ? stopScan : startScan}
            style={{ padding: '0.5rem 1rem', borderRadius: 6, border: 'none', background: scanMode ? '#6b7280' : '#d97706', color: '#fff', cursor: 'pointer' }}
          >
            {scanMode ? 'Scan abbrechen' : 'Karte scannen'}
          </button>
          <button
            type="button"
            onClick={() => setCreateOpen(true)}
            style={{ padding: '0.5rem 1rem', borderRadius: 6, border: '1px solid #d1d5db', background: '#fff', cursor: 'pointer' }}
          >
            Neue Karte
          </button>
        </div>
      </div>

      {scanMode && (
        <div style={{ marginBottom: '1.5rem', padding: '1rem', border: '1px solid #fcd34d', background: '#fffbeb', borderRadius: 8 }}>
          {!scannedUid ? (
            <div style={{ color: '#92400e' }}>
              <strong>Leser bereit</strong> – jetzt eine Karte auf den Leser legen…
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              <div>
                <span style={{ fontSize: 13, color: '#92400e', fontWeight: 600 }}>Gescannte UID: </span>
                <span style={{ fontFamily: 'monospace', fontSize: 15 }}>{scannedUid}</span>
              </div>

              {lookupLoading && <div style={{ color: '#6b7280' }}>Prüfe…</div>}

              {!lookupLoading && lookup?.status === 'free' && (
                <>
                  <div style={{ color: '#15803d', fontWeight: 600 }}>Frei – noch keinem Profil zugeordnet.</div>
                  <div style={{ display: 'flex', gap: 8 }}>
                    <button
                      type="button"
                      onClick={adoptScannedUid}
                      style={{ padding: '0.5rem 1rem', borderRadius: 6, border: 'none', background: '#2563eb', color: '#fff', cursor: 'pointer' }}
                    >
                      Diese UID übernehmen
                    </button>
                    <button
                      type="button"
                      onClick={rescan}
                      style={{ padding: '0.5rem 1rem', borderRadius: 6, border: '1px solid #d1d5db', background: '#fff', cursor: 'pointer' }}
                    >
                      Andere Karte scannen
                    </button>
                  </div>
                </>
              )}

              {!lookupLoading && lookup?.status === 'assigned' && (
                <>
                  <div style={{ color: '#b91c1c', fontWeight: 600 }}>
                    Belegt
                    {lookup.profile_name ? ` von „${lookup.profile_name}“` : ''}
                    {lookup.profile_id === profileId ? ' (dieses Profil)' : ''}.
                  </div>
                  <div style={{ color: '#6b7280', fontSize: 14 }}>
                    {lookup.label ? `Label: ${lookup.label}. ` : ''}
                    {lookup.has_binding ? `Playlist: ${lookup.binding_name ?? '—'}.` : 'Keine Playlist gebunden.'}
                  </div>
                  <div>
                    <button
                      type="button"
                      onClick={rescan}
                      style={{ padding: '0.5rem 1rem', borderRadius: 6, border: '1px solid #d1d5db', background: '#fff', cursor: 'pointer' }}
                    >
                      Andere Karte scannen
                    </button>
                  </div>
                </>
              )}
            </div>
          )}
        </div>
      )}

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
          <div style={{ background: '#fff', padding: '1.5rem', borderRadius: 8, minWidth: 360 }}>
            <h3 style={{ marginTop: 0 }}>Playlist verbinden: {bindingCard.card_uid}</h3>
            <label style={{ display: 'block', marginBottom: 8 }}>
              Playlist aus deiner Spotify-Bibliothek
              {playlistsLoading ? (
                <p style={{ color: '#6b7280', fontSize: 14, marginTop: 4 }}>Lade Playlists…</p>
              ) : playlistsError ? (
                <p style={{ color: '#dc2626', fontSize: 14, marginTop: 4 }}>
                  Playlists konnten nicht geladen werden – ist Spotify für dieses Profil verbunden?
                </p>
              ) : (
                <select
                  value={bindingPlaylistId}
                  onChange={(e) => setBindingPlaylistId(e.target.value)}
                  style={{ display: 'block', width: '100%', padding: '0.5rem', marginTop: 2, border: '1px solid #d1d5db', borderRadius: 4 }}
                >
                  <option value="">— Keine —</option>
                  {(spotifyPlaylists?.items ?? []).map((p) => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </select>
              )}
            </label>
            {!playlistsLoading && !playlistsError && (spotifyPlaylists?.items?.length ?? 0) === 0 && (
              <p style={{ color: '#6b7280', fontSize: 13, marginTop: 0 }}>
                Keine Playlists gefunden. Lege in Spotify eine Playlist an oder verbinde das Profil mit Spotify.
              </p>
            )}
            <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
              <button
                type="button"
                onClick={saveBinding}
                disabled={bindingBusy || setBindingMutation.isPending}
                style={{ padding: '0.5rem 1rem', borderRadius: 6, border: 'none', background: '#2563eb', color: '#fff', cursor: 'pointer' }}
              >
                {bindingBusy || setBindingMutation.isPending ? 'Speichern…' : 'Speichern'}
              </button>
              <button
                type="button"
                onClick={() => { setBindingCard(null); setBindingPlaylistId(''); setBindingError(null); }}
                style={{ padding: '0.5rem 1rem', borderRadius: 6, border: '1px solid #d1d5db', background: '#fff', cursor: 'pointer' }}
              >
                Abbrechen
              </button>
            </div>
            {(bindingError || setBindingMutation.isError) && (
              <p style={{ color: '#dc2626', fontSize: 14, marginTop: 8 }}>
                {bindingError ?? (setBindingMutation.error as Error).message}
              </p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

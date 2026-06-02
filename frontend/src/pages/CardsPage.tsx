import { useState, useEffect, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  CreditCard, ChevronLeft, RefreshCw, Plus, X, Scan, Check, Trash2, Music2,
} from 'lucide-react';
import type { RfidCardDto } from '../api/endpoints/rfid';
import { scanApi } from '../api/endpoints/scan';
import {
  useRfidCards,
  usePlaylistReferences,
  useCreatePlaylistReference,
  useCreateRfidCard,
  useUpdateRfidCard,
  useDeleteRfidCard,
  useSetCardBinding,
  useCardLookup,
} from '../hooks/useRfidCards';
import { useSpotifyPlaylists } from '../hooks/useSpotifyPlaylists';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { cn } from '@/lib/utils';

const NONE_VALUE = '__none__';

// ─── Binding-Zelle ────────────────────────────────────────────────────────────

interface BindingCellProps {
  card: RfidCardDto;
  profileId: string;
  isEditing: boolean;
  onDone: () => void;
}

function BindingCell({ card, profileId, isEditing, onDone }: BindingCellProps) {
  const { data: plRefs, isLoading: refsLoading } = usePlaylistReferences(profileId, isEditing);
  const { data: spotifyPls, isLoading: plsLoading } = useSpotifyPlaylists(profileId, isEditing);
  const setBinding = useSetCardBinding(profileId);
  const createRef = useCreatePlaylistReference(profileId);

  const [selectedId, setSelectedId] = useState<string>(NONE_VALUE);
  const [busy, setBusy] = useState(false);
  const [bindErr, setBindErr] = useState<string | null>(null);

  // Sobald Refs geladen sind: aktuellen Wert vorausfüllen
  useEffect(() => {
    if (!isEditing || refsLoading) return;
    if (!card.binding?.id) {
      setSelectedId(NONE_VALUE);
      return;
    }
    const match = (plRefs?.items ?? []).find((r) => r.id === card.binding!.id);
    setSelectedId(match?.spotify_playlist_id ?? NONE_VALUE);
  }, [isEditing, refsLoading, card.binding, plRefs]);

  const handleSelect = async (v: string) => {
    const spotifyId = v === NONE_VALUE ? null : v;
    setSelectedId(v);
    setBindErr(null);
    setBusy(true);
    try {
      if (!spotifyId) {
        await setBinding.mutateAsync({ cardId: card.id, refId: null });
      } else {
        const refs = plRefs?.items ?? [];
        const existing = refs.find((r) => r.spotify_playlist_id === spotifyId);
        let refId = existing?.id;
        if (!refId) {
          const pl = (spotifyPls?.items ?? []).find((p) => p.id === spotifyId);
          const newRef = await createRef.mutateAsync({
            spotify_playlist_id: spotifyId,
            name: pl?.name ?? spotifyId,
            owner_id: pl?.owner_id ?? null,
          });
          refId = newRef.id;
        }
        await setBinding.mutateAsync({ cardId: card.id, refId });
      }
      onDone();
    } catch (e) {
      setBindErr((e as Error).message ?? 'Fehler beim Speichern');
    } finally {
      setBusy(false);
    }
  };

  if (!isEditing) {
    return card.binding ? (
      <Badge variant="secondary" className="font-normal max-w-[180px] truncate">
        <Music2 className="h-3 w-3 mr-1 shrink-0" />
        {card.binding.name}
      </Badge>
    ) : (
      <span className="text-muted-foreground text-sm">—</span>
    );
  }

  const loading = refsLoading || plsLoading;

  return (
    <div className="flex flex-col gap-1">
      <div className="flex items-center gap-1.5">
        {loading ? (
          <Skeleton className="h-7 w-44" />
        ) : (
          <Select value={selectedId} onValueChange={handleSelect} disabled={busy}>
            <SelectTrigger className="h-7 w-52 text-xs">
              <SelectValue placeholder="Playlist wählen…" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={NONE_VALUE}>— Keine —</SelectItem>
              {(spotifyPls?.items ?? []).map((p) => (
                <SelectItem key={p.id} value={p.id}>{p.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
        <Button
          variant="ghost"
          size="sm"
          className="h-7 w-7 p-0 text-muted-foreground"
          onClick={onDone}
          disabled={busy}
        >
          <X className="h-3.5 w-3.5" />
        </Button>
      </div>
      {bindErr && <p className="text-xs text-destructive">{bindErr}</p>}
    </div>
  );
}

// ─── Label-Zelle ─────────────────────────────────────────────────────────────

interface LabelCellProps {
  card: RfidCardDto;
  profileId: string;
  isEditing: boolean;
  onStartEdit: () => void;
  onDone: () => void;
}

function LabelCell({ card, profileId, isEditing, onStartEdit, onDone }: LabelCellProps) {
  const update = useUpdateRfidCard(profileId);
  const [value, setValue] = useState(card.label ?? '');
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (isEditing) {
      setValue(card.label ?? '');
      setTimeout(() => inputRef.current?.focus(), 0);
    }
  }, [isEditing, card.label]);

  const save = () => {
    const newLabel = value.trim() || null;
    if (newLabel === card.label) { onDone(); return; }
    update.mutate({ cardId: card.id, label: newLabel }, { onSuccess: onDone, onError: onDone });
  };

  if (!isEditing) {
    return (
      <button
        type="button"
        onClick={onStartEdit}
        className="text-left text-sm hover:text-primary transition-colors min-w-[80px]"
        title="Klicken zum Bearbeiten"
      >
        {card.label ?? <span className="text-muted-foreground italic">kein Label</span>}
      </button>
    );
  }

  return (
    <div className="flex items-center gap-1">
      <Input
        ref={inputRef}
        value={value}
        onChange={(e) => setValue(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter') save();
          if (e.key === 'Escape') onDone();
        }}
        onBlur={save}
        className="h-7 text-xs w-36"
        placeholder="Label eingeben…"
        disabled={update.isPending}
      />
      <Button
        variant="ghost"
        size="sm"
        className="h-7 w-7 p-0 text-success"
        onClick={save}
        disabled={update.isPending}
      >
        <Check className="h-3.5 w-3.5" />
      </Button>
    </div>
  );
}

// ─── Scan-to-Create Panel ─────────────────────────────────────────────────────

interface ScanPanelProps {
  profileId: string;
  onAdoptUid: (uid: string) => void;
  onStop: () => void;
}

function ScanPanel({ profileId, onAdoptUid, onStop }: ScanPanelProps) {
  const [scannedUid, setScannedUid] = useState<string | null>(null);
  const [baseline, setBaseline] = useState<{ set: boolean; id: string | null }>({ set: false, id: null });

  const { data: scanEvents } = useQuery({
    queryKey: ['scan-events', 'enroll'],
    queryFn: () => scanApi.listEvents({ limit: 5 }),
    refetchInterval: 2000,
    enabled: !scannedUid,
  });

  const { data: lookup, isLoading: lookupLoading } = useCardLookup(scannedUid);

  useEffect(() => {
    const events = scanEvents?.items ?? [];
    const newest = events[0];
    if (!baseline.set) {
      setBaseline({ set: true, id: newest?.id ?? null });
      return;
    }
    if (!scannedUid && newest && newest.id !== baseline.id) {
      setScannedUid(newest.card_uid_raw);
    }
  }, [scanEvents, baseline, scannedUid]);

  const rescan = () => {
    setScannedUid(null);
    setBaseline({ set: false, id: null });
  };

  return (
    <div className="mx-6 mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
      <div className="flex items-center justify-between mb-2">
        <div className="flex items-center gap-2">
          <Scan className="h-4 w-4 text-amber-700" />
          <span className="text-sm font-medium text-amber-800">Karte scannen</span>
        </div>
        <Button variant="ghost" size="sm" className="h-7 w-7 p-0 text-amber-700" onClick={onStop}>
          <X className="h-3.5 w-3.5" />
        </Button>
      </div>

      {!scannedUid ? (
        <p className="text-sm text-amber-700">Leser bereit – jetzt eine Karte auflegen…</p>
      ) : (
        <div className="space-y-2">
          <div className="flex items-center gap-2 text-sm">
            <span className="text-amber-700 font-medium">Gescannte UID:</span>
            <code className="bg-amber-100 px-1.5 py-0.5 rounded text-amber-900 text-xs">{scannedUid}</code>
          </div>

          {lookupLoading && <p className="text-sm text-amber-600">Prüfe…</p>}

          {!lookupLoading && lookup?.status === 'free' && (
            <div className="flex items-center gap-2">
              <Badge variant="success">Frei</Badge>
              <span className="text-sm text-amber-700">Noch keinem Profil zugeordnet.</span>
              <Button
                size="sm"
                className="h-7 text-xs"
                onClick={() => { onAdoptUid(scannedUid); onStop(); }}
              >
                UID übernehmen
              </Button>
              <Button variant="outline" size="sm" className="h-7 text-xs" onClick={rescan}>
                Neu scannen
              </Button>
            </div>
          )}

          {!lookupLoading && lookup?.status === 'assigned' && (
            <div className="flex items-center gap-2 flex-wrap">
              <Badge variant="destructive">Belegt</Badge>
              <span className="text-sm text-amber-700">
                {lookup.profile_name ? `von „${lookup.profile_name}"` : ''}
                {lookup.profile_id === profileId ? ' (dieses Profil)' : ''}
                {lookup.label ? ` · Label: ${lookup.label}` : ''}
              </span>
              <Button variant="outline" size="sm" className="h-7 text-xs" onClick={rescan}>
                Neu scannen
              </Button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

// ─── Karte anlegen Panel ──────────────────────────────────────────────────────

interface CreateCardPanelProps {
  profileId: string;
  initialUid?: string;
  onDone: () => void;
}

function CreateCardPanel({ profileId, initialUid = '', onDone }: CreateCardPanelProps) {
  const createMutation = useCreateRfidCard(profileId);
  const [uid, setUid] = useState(initialUid);
  const [label, setLabel] = useState('');

  useEffect(() => { setUid(initialUid); }, [initialUid]);

  const handleCreate = () => {
    if (!uid.trim()) return;
    createMutation.mutate(
      { card_uid: uid.trim(), label: label.trim() || null },
      { onSuccess: () => { setUid(''); setLabel(''); onDone(); } },
    );
  };

  return (
    <div className="border-t bg-muted/20 px-6 py-4">
      <h3 className="text-sm font-medium mb-3">Neue Karte anlegen</h3>
      <div className="flex items-end gap-3 flex-wrap">
        <div className="space-y-1">
          <label className="text-xs text-muted-foreground">Card UID <span className="text-destructive">*</span></label>
          <Input
            value={uid}
            onChange={(e) => setUid(e.target.value)}
            placeholder="z. B. 04A1B2C3D4E5F6"
            className="h-8 text-sm w-52"
            onKeyDown={(e) => { if (e.key === 'Enter') handleCreate(); }}
          />
        </div>
        <div className="space-y-1">
          <label className="text-xs text-muted-foreground">Label (optional)</label>
          <Input
            value={label}
            onChange={(e) => setLabel(e.target.value)}
            placeholder="z. B. Kinderzimmer"
            className="h-8 text-sm w-44"
            onKeyDown={(e) => { if (e.key === 'Enter') handleCreate(); }}
          />
        </div>
        <div className="flex gap-2">
          <Button
            size="sm"
            className="h-8"
            onClick={handleCreate}
            disabled={!uid.trim() || createMutation.isPending}
          >
            {createMutation.isPending ? 'Anlegen…' : 'Anlegen'}
          </Button>
          <Button variant="outline" size="sm" className="h-8" onClick={onDone}>
            Abbrechen
          </Button>
        </div>
      </div>
      {createMutation.isError && (
        <p className="text-xs text-destructive mt-2">{(createMutation.error as Error).message}</p>
      )}
    </div>
  );
}

// ─── Hauptseite ───────────────────────────────────────────────────────────────

export function CardsPage() {
  const { profileId } = useParams<{ profileId: string }>();

  const { data, isLoading, error, refetch, isFetching } = useRfidCards(profileId);
  const deleteMutation = useDeleteRfidCard(profileId!);

  const [editingLabelId, setEditingLabelId] = useState<string | null>(null);
  const [editingBindingId, setEditingBindingId] = useState<string | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<RfidCardDto | null>(null);
  const [scanMode, setScanMode] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [adoptedUid, setAdoptedUid] = useState('');

  const items = data?.items ?? [];

  const openCreate = (uid = '') => {
    setAdoptedUid(uid);
    setCreateOpen(true);
  };

  if (!profileId) return null;

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between border-b px-6 py-4 shrink-0">
        <div className="flex items-center gap-3">
          <Link
            to={`/profiles/${profileId}`}
            className="text-muted-foreground hover:text-foreground transition-colors"
          >
            <ChevronLeft className="h-4 w-4" />
          </Link>
          <div>
            <h1 className="text-lg font-semibold tracking-tight">RFID-Karten</h1>
            {!isLoading && (
              <p className="text-sm text-muted-foreground">{items.length} Karte{items.length !== 1 ? 'n' : ''}</p>
            )}
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant={scanMode ? 'secondary' : 'outline'}
            size="sm"
            onClick={() => { setScanMode((v) => !v); }}
          >
            <Scan className="h-4 w-4" />
            {scanMode ? 'Scan beenden' : 'Karte scannen'}
          </Button>
          <Button variant="outline" size="sm" onClick={() => refetch()} disabled={isFetching}>
            <RefreshCw className={cn('h-4 w-4', isFetching && 'animate-spin')} />
          </Button>
        </div>
      </div>

      {/* Scan Panel */}
      {scanMode && (
        <div className="shrink-0 pt-4">
          <ScanPanel
            profileId={profileId}
            onAdoptUid={(uid) => openCreate(uid)}
            onStop={() => setScanMode(false)}
          />
        </div>
      )}

      {/* Tabelle */}
      <div className="flex-1 overflow-auto">
        {isLoading ? (
          <div className="p-6 space-y-3">
            {[1, 2, 3].map((i) => <Skeleton key={i} className="h-12 w-full" />)}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center h-64 text-center">
            <p className="text-sm text-destructive">Karten konnten nicht geladen werden.</p>
            <Button variant="outline" size="sm" className="mt-3" onClick={() => refetch()}>
              Erneut versuchen
            </Button>
          </div>
        ) : items.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-64 text-center">
            <CreditCard className="h-8 w-8 text-muted-foreground mb-3" />
            <p className="text-sm text-muted-foreground mb-3">
              Noch keine Karten für dieses Profil.
            </p>
            <Button size="sm" onClick={() => openCreate()}>
              <Plus className="h-4 w-4" />
              Erste Karte anlegen
            </Button>
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="w-[200px]">UID</TableHead>
                <TableHead className="w-[200px]">Label</TableHead>
                <TableHead>Playlist</TableHead>
                <TableHead className="w-[120px] text-right">Aktionen</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((card) => (
                <TableRow key={card.id}>
                  {/* UID */}
                  <TableCell>
                    <code className="font-mono text-xs bg-muted px-1.5 py-0.5 rounded">
                      {card.card_uid}
                    </code>
                  </TableCell>

                  {/* Label */}
                  <TableCell>
                    <LabelCell
                      card={card}
                      profileId={profileId}
                      isEditing={editingLabelId === card.id}
                      onStartEdit={() => {
                        setEditingBindingId(null);
                        setEditingLabelId(card.id);
                      }}
                      onDone={() => setEditingLabelId(null)}
                    />
                  </TableCell>

                  {/* Playlist / Binding */}
                  <TableCell>
                    <BindingCell
                      card={card}
                      profileId={profileId}
                      isEditing={editingBindingId === card.id}
                      onDone={() => setEditingBindingId(null)}
                    />
                  </TableCell>

                  {/* Aktionen */}
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-1">
                      <Button
                        variant="ghost"
                        size="sm"
                        className={cn(
                          'h-7 text-xs',
                          editingBindingId === card.id && 'text-primary',
                        )}
                        onClick={() => {
                          setEditingLabelId(null);
                          setEditingBindingId(
                            editingBindingId === card.id ? null : card.id,
                          );
                        }}
                      >
                        <Music2 className="h-3.5 w-3.5" />
                        Playlist
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 w-7 p-0 text-destructive hover:text-destructive"
                        onClick={() => setDeleteTarget(card)}
                        disabled={deleteMutation.isPending}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </div>

      {/* Neue Karte Button / Create Panel */}
      {!createOpen && items.length > 0 && (
        <div className="border-t px-6 py-3 shrink-0">
          <Button variant="outline" size="sm" onClick={() => openCreate()}>
            <Plus className="h-4 w-4" />
            Neue Karte
          </Button>
        </div>
      )}

      {createOpen && (
        <div className="shrink-0">
          <CreateCardPanel
            profileId={profileId}
            initialUid={adoptedUid}
            onDone={() => { setCreateOpen(false); setAdoptedUid(''); }}
          />
        </div>
      )}

      {/* Löschen-Bestätigung */}
      <AlertDialog open={!!deleteTarget} onOpenChange={(v) => !v && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Karte löschen?</AlertDialogTitle>
            <AlertDialogDescription>
              Karte <code className="font-mono text-xs">{deleteTarget?.card_uid}</code>
              {deleteTarget?.label ? ` (${deleteTarget.label})` : ''} wird unwiderruflich gelöscht.
              Eine eventuell vorhandene Playlist-Bindung wird ebenfalls entfernt.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Abbrechen</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive hover:bg-destructive/90"
              onClick={() => {
                if (!deleteTarget) return;
                deleteMutation.mutate(deleteTarget.id, { onSuccess: () => setDeleteTarget(null) });
              }}
            >
              Löschen
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

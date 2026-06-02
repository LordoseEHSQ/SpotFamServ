import { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Plus, Search, RefreshCw, Trash2, ChevronRight, Wand2 } from 'lucide-react';
import { useProfiles, useCreateProfile, useDeleteProfile } from '@/hooks/useProfiles';
import { type FamilyProfileDto } from '@/api/endpoints/profiles';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDateRelative } from '@/lib/utils';

function SpotifyStatusBadge({ status }: { status: FamilyProfileDto['spotify_status'] }) {
  if (status === 'connected') return <Badge variant="success">Verbunden</Badge>;
  if (status === 'reauth_required') return <Badge variant="warning">Neu verbinden</Badge>;
  return <Badge variant="muted">Nicht verbunden</Badge>;
}

function ProfileStatusBadge({ status }: { status: FamilyProfileDto['status'] }) {
  if (status === 'active') return <Badge variant="success">Aktiv</Badge>;
  return <Badge variant="muted">Inaktiv</Badge>;
}

function SetupBadge({ complete, percent }: { complete: boolean; percent: number }) {
  if (complete) return <Badge variant="success">Vollständig</Badge>;
  if (percent > 0) return <Badge variant="warning">{percent}% Setup</Badge>;
  return <Badge variant="muted">Kein Setup</Badge>;
}

function CreateProfileDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (v: boolean) => void }) {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const createProfile = useCreateProfile();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;
    await createProfile.mutateAsync({ name: name.trim(), description: description.trim() || null });
    setName('');
    setDescription('');
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Neuer Teilnehmer</DialogTitle>
          <DialogDescription>Erstelle einen neuen Teilnehmer-Datensatz.</DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="name">Anzeigename *</Label>
            <Input
              id="name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="z. B. Lars"
              autoFocus
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="desc">Notizen</Label>
            <Textarea
              id="desc"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Optional"
              rows={2}
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Abbrechen</Button>
            <Button type="submit" disabled={!name.trim() || createProfile.isPending}>
              {createProfile.isPending ? 'Wird erstellt…' : 'Erstellen'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function ProfilesPage() {
  const navigate = useNavigate();
  const { data, isLoading, error, refetch, isFetching } = useProfiles();
  const deleteProfile = useDeleteProfile();
  const [search, setSearch] = useState('');
  const [createOpen, setCreateOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<FamilyProfileDto | null>(null);

  const filtered = useMemo(() => {
    const q = search.toLowerCase();
    return (data?.items ?? []).filter(
      (p) => p.name.toLowerCase().includes(q) || (p.description ?? '').toLowerCase().includes(q)
    );
  }, [data, search]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    await deleteProfile.mutateAsync(deleteTarget.id);
    setDeleteTarget(null);
  };

  return (
    <div className="flex flex-col h-full">
      {/* Page Header */}
      <div className="flex items-center justify-between border-b px-6 py-4 shrink-0">
        <div>
          <h1 className="text-lg font-semibold tracking-tight">Teilnehmer</h1>
          <p className="text-sm text-muted-foreground">
            {data?.items.length ?? 0} Datensätze
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => refetch()} disabled={isFetching}>
            <RefreshCw className={`h-4 w-4 ${isFetching ? 'animate-spin' : ''}`} />
          </Button>
          <Button size="sm" onClick={() => setCreateOpen(true)}>
            <Plus className="h-4 w-4" />
            Neuer Teilnehmer
          </Button>
        </div>
      </div>

      {/* Toolbar */}
      <div className="flex items-center gap-3 px-6 py-3 border-b shrink-0 bg-muted/20">
        <div className="relative flex-1 max-w-xs">
          <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Suchen…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-8 h-8 text-sm"
          />
        </div>
      </div>

      {/* Table */}
      <div className="flex-1 overflow-auto">
        {isLoading ? (
          <div className="p-6 space-y-3">
            {[1, 2, 3].map((i) => <Skeleton key={i} className="h-12 w-full" />)}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center h-64 text-center">
            <p className="text-sm text-destructive font-medium">Fehler beim Laden</p>
            <Button variant="outline" size="sm" className="mt-3" onClick={() => refetch()}>Erneut versuchen</Button>
          </div>
        ) : filtered.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-64 text-center">
            <p className="text-sm text-muted-foreground">
              {search ? 'Keine Ergebnisse für diese Suche.' : 'Noch keine Teilnehmer vorhanden.'}
            </p>
            {!search && (
              <Button size="sm" className="mt-3" onClick={() => setCreateOpen(true)}>
                <Plus className="h-4 w-4" />
                Ersten Teilnehmer anlegen
              </Button>
            )}
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead>Name</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Spotify</TableHead>
                <TableHead>Standardlautsprecher</TableHead>
                <TableHead>Setup</TableHead>
                <TableHead>Letzte Aktivität</TableHead>
                <TableHead className="w-10" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {filtered.map((profile) => (
                <TableRow
                  key={profile.id}
                  onClick={() => navigate(`/profiles/${profile.id}`)}
                  className="cursor-pointer"
                >
                  <TableCell>
                    <div className="flex flex-col">
                      <span className="font-medium">{profile.name}</span>
                      {profile.description && (
                        <span className="text-xs text-muted-foreground truncate max-w-48">{profile.description}</span>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>
                    <ProfileStatusBadge status={profile.status ?? 'active'} />
                  </TableCell>
                  <TableCell>
                    <div className="flex flex-col gap-0.5">
                      <SpotifyStatusBadge status={profile.spotify_status ?? 'not_connected'} />
                      {profile.spotify_user_display_name && (
                        <span className="text-xs text-muted-foreground">{profile.spotify_user_display_name}</span>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>
                    <span className="text-sm">
                      {profile.default_device_name ?? <span className="text-muted-foreground">—</span>}
                    </span>
                  </TableCell>
                  <TableCell>
                    <SetupBadge complete={profile.setup_complete ?? false} percent={profile.setup_percent ?? 0} />
                  </TableCell>
                  <TableCell>
                    <span className="text-sm text-muted-foreground">
                      {formatDateRelative(profile.last_activity_at)}
                    </span>
                  </TableCell>
                  <TableCell onClick={(e) => e.stopPropagation()}>
                    <div className="flex items-center gap-1">
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8"
                        onClick={() => navigate(`/profiles/${profile.id}/setup`)}
                        title="Setup fortsetzen"
                      >
                        <Wand2 className="h-3.5 w-3.5" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-destructive hover:text-destructive"
                        onClick={() => setDeleteTarget(profile)}
                        title="Löschen"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8"
                        onClick={() => navigate(`/profiles/${profile.id}`)}
                      >
                        <ChevronRight className="h-4 w-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </div>

      <CreateProfileDialog open={createOpen} onOpenChange={setCreateOpen} />

      <AlertDialog open={!!deleteTarget} onOpenChange={(v) => !v && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Teilnehmer löschen?</AlertDialogTitle>
            <AlertDialogDescription>
              Der Datensatz <strong>{deleteTarget?.name}</strong> wird unwiderruflich gelöscht.
              Alle zugehörigen RFID-Karten und Bindungen werden ebenfalls entfernt.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Abbrechen</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              onClick={handleDelete}
            >
              Endgültig löschen
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

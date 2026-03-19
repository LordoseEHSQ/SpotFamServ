import { useState } from 'react';
import { Plus, Search, Music2, Loader2, RefreshCw, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { useSpotifyPlaylists, useCreateSpotifyPlaylist } from '@/hooks/useSpotifyPlaylists';
import type { SpotifyPlaylistItem } from '@/api/endpoints/spotify';
import type { ApiError } from '@/api/client';

interface PlaylistListProps {
  profileId: string;
  selectedId: string | null;
  onSelect: (playlist: SpotifyPlaylistItem) => void;
  spotifyConnected: boolean;
}

export function PlaylistList({ profileId, selectedId, onSelect, spotifyConnected }: PlaylistListProps) {
  const { data, isLoading, refetch, isFetching } = useSpotifyPlaylists(profileId, spotifyConnected);
  const createPlaylist = useCreateSpotifyPlaylist(profileId);

  const [search, setSearch] = useState('');
  const [showCreate, setShowCreate] = useState(false);
  const [newName, setNewName] = useState('');
  const [newDesc, setNewDesc] = useState('');
  const [createError, setCreateError] = useState<string | null>(null);

  const filtered = (data?.items ?? []).filter(
    (p) => p.name.toLowerCase().includes(search.toLowerCase()),
  );

  const handleCreate = async () => {
    if (!newName.trim()) return;
    setCreateError(null);
    try {
      await createPlaylist.mutateAsync({ name: newName.trim(), description: newDesc.trim() || undefined });
      setShowCreate(false);
      setNewName('');
      setNewDesc('');
    } catch (err: unknown) {
      const apiErr = err as ApiError;
      const status = apiErr?.status ?? 0;
      if (status === 403) {
        setCreateError(
          'Fehlende Spotify-Berechtigung. Bitte Spotify-Verbindung im Tab „Spotify" trennen und neu verbinden, um Playlist-Zugriff zu erteilen.',
        );
      } else {
        setCreateError(apiErr?.detail ?? 'Unbekannter Fehler beim Erstellen der Playlist.');
      }
    }
  };

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="px-3 py-2.5 border-b flex items-center justify-between gap-2 shrink-0">
        <span className="text-sm font-medium">Playlists</span>
        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="icon"
            className="h-7 w-7"
            onClick={() => refetch()}
            disabled={isFetching}
            title="Aktualisieren"
          >
            <RefreshCw className={cn('h-3.5 w-3.5', isFetching && 'animate-spin')} />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            className="h-7 w-7"
            onClick={() => setShowCreate(true)}
            disabled={!spotifyConnected}
            title="Playlist erstellen"
          >
            <Plus className="h-3.5 w-3.5" />
          </Button>
        </div>
      </div>

      {/* Search */}
      <div className="px-3 py-2 border-b shrink-0">
        <div className="relative">
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
          <Input
            className="pl-8 h-7 text-xs"
            placeholder="Playlist suchen…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      </div>

      {/* List */}
      <div className="flex-1 overflow-y-auto">
        {!spotifyConnected ? (
          <div className="px-3 py-8 text-center text-sm text-muted-foreground">
            <Music2 className="h-8 w-8 mx-auto mb-2 text-muted-foreground/40" />
            Spotify nicht verbunden
          </div>
        ) : isLoading ? (
          <div className="space-y-1 p-2">
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className="h-9 w-full rounded" />
            ))}
          </div>
        ) : filtered.length === 0 ? (
          <div className="px-3 py-8 text-center text-sm text-muted-foreground">
            <Music2 className="h-8 w-8 mx-auto mb-2 text-muted-foreground/40" />
            {search ? 'Keine Ergebnisse' : 'Keine Playlists gefunden'}
          </div>
        ) : (
          <ul className="p-1.5 space-y-0.5">
            {filtered.map((playlist) => (
              <li key={playlist.id}>
                <button
                  type="button"
                  onClick={() => onSelect(playlist)}
                  className={cn(
                    'w-full text-left px-2.5 py-2 rounded-md text-sm transition-colors',
                    'hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    selectedId === playlist.id && 'bg-primary/10 text-primary font-medium',
                  )}
                >
                  <div className="flex items-center gap-2 min-w-0">
                    <Music2 className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                    <span className="min-w-0 truncate flex-1">{playlist.name}</span>
                  </div>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      {/* Create Dialog */}
      <Dialog open={showCreate} onOpenChange={(open) => { setShowCreate(open); if (!open) setCreateError(null); }}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>Neue Playlist erstellen</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <div className="rounded-md border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30 px-3 py-2 text-xs text-amber-800 dark:text-amber-300">
              <strong>Hinweis:</strong> Das Erstellen von Playlists erfordert seit Nov 2024 eine Spotify Developer App-Freigabe (Extended Quota). Falls dies fehlschlägt, muss die App im Spotify Developer Dashboard für diesen Endpoint beantragt werden.
            </div>
            {createError && (
              <div className="flex gap-2 items-start rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                <AlertCircle className="h-4 w-4 mt-0.5 shrink-0" />
                <span>{createError}</span>
              </div>
            )}
            <div className="space-y-1.5">
              <Label htmlFor="pl-name">Name *</Label>
              <Input
                id="pl-name"
                value={newName}
                onChange={(e) => setNewName(e.target.value)}
                placeholder="Meine Playlist"
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="pl-desc">Beschreibung</Label>
              <Textarea
                id="pl-desc"
                value={newDesc}
                onChange={(e) => setNewDesc(e.target.value)}
                rows={2}
                placeholder="Optional"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setShowCreate(false); setCreateError(null); }}>Abbrechen</Button>
            <Button
              onClick={handleCreate}
              disabled={!newName.trim() || createPlaylist.isPending}
            >
              {createPlaylist.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
              Erstellen
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

import { useState, useCallback } from 'react';
import { Search, Music2, Play, Plus, Loader2, Disc3 } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Separator } from '@/components/ui/separator';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { useSpotifySearch } from '@/hooks/useSpotifySearch';
import { usePlaySpotify } from '@/hooks/useSpotifyPlayer';
import { useAddTracksToPlaylist } from '@/hooks/useSpotifyPlaylists';
import type { SpotifyPlaylistItem, SpotifySearchTrackItem } from '@/api/endpoints/spotify';
import type { FamilyProfileDto } from '@/api/endpoints/profiles';

function formatMs(ms: number): string {
  const total = Math.floor(ms / 1000);
  const m = Math.floor(total / 60);
  const s = total % 60;
  return `${m}:${s.toString().padStart(2, '0')}`;
}

interface SpotifySearchProps {
  profile: FamilyProfileDto;
  playlists: SpotifyPlaylistItem[];
}

export function SpotifySearch({ profile, playlists }: SpotifySearchProps) {
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [addTarget, setAddTarget] = useState<SpotifySearchTrackItem | null>(null);

  const debounce = useCallback(
    (() => {
      let timer: ReturnType<typeof setTimeout>;
      return (val: string) => {
        clearTimeout(timer);
        timer = setTimeout(() => setDebouncedQuery(val), 400);
      };
    })(),
    [],
  );

  const handleQueryChange = (val: string) => {
    setQuery(val);
    debounce(val);
  };

  const { data, isLoading } = useSpotifySearch(profile.id, debouncedQuery);
  const play = usePlaySpotify(profile.id);
  const addTracks = useAddTracksToPlaylist(profile.id);

  const handleAddToPlaylist = (playlistId: string) => {
    if (!addTarget) return;
    addTracks.mutate({ playlistId, uris: [addTarget.uri] });
    setAddTarget(null);
  };

  return (
    <div className="flex flex-col h-full">
      {/* Search Input */}
      <div className="px-3 py-2.5 border-b shrink-0">
        <div className="relative">
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            className="pl-9"
            placeholder="Tracks, Playlists, Alben suchen…"
            value={query}
            onChange={(e) => handleQueryChange(e.target.value)}
          />
        </div>
      </div>

      <ScrollArea className="flex-1">
        {debouncedQuery.trim().length < 2 ? (
          <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
            <Search className="h-10 w-10 mb-3 opacity-40" />
            <p className="text-sm">Mindestens 2 Zeichen eingeben</p>
          </div>
        ) : isLoading ? (
          <div className="space-y-2 p-4">
            {Array.from({ length: 5 }).map((_, i) => (
              <Skeleton key={i} className="h-12 w-full" />
            ))}
          </div>
        ) : !data || (data.tracks.length === 0 && data.playlists.length === 0) ? (
          <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
            <Music2 className="h-10 w-10 mb-3 opacity-40" />
            <p className="text-sm">Keine Ergebnisse für „{debouncedQuery}"</p>
          </div>
        ) : (
          <div className="p-3 space-y-4">
            {/* Tracks */}
            {data.tracks.length > 0 && (
              <section>
                <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
                  <Music2 className="h-3.5 w-3.5" />
                  Tracks
                </h4>
                <ul className="space-y-1">
                  {data.tracks.map((track) => (
                    <li key={track.id} className="flex items-center gap-2.5 p-2 rounded-md hover:bg-muted/50 group">
                      {track.album_cover_url ? (
                        <img src={track.album_cover_url} alt="" className="h-9 w-9 rounded object-cover shrink-0" />
                      ) : (
                        <div className="h-9 w-9 rounded bg-muted shrink-0 flex items-center justify-center">
                          <Music2 className="h-4 w-4 text-muted-foreground" />
                        </div>
                      )}
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-sm truncate">{track.name}</p>
                        <p className="text-xs text-muted-foreground truncate">{track.artists.join(', ')}</p>
                      </div>
                      <span className="text-xs text-muted-foreground shrink-0">{formatMs(track.duration_ms)}</span>
                      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-7 w-7"
                          onClick={() => play.mutate({ contextUri: track.uri, deviceId: profile.default_spotify_device_id ?? undefined })}
                          disabled={play.isPending}
                          title="Abspielen"
                        >
                          <Play className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-7 w-7"
                          onClick={() => setAddTarget(track)}
                          title="Zu Playlist hinzufügen"
                        >
                          <Plus className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    </li>
                  ))}
                </ul>
              </section>
            )}

            {data.tracks.length > 0 && data.playlists.length > 0 && <Separator />}

            {/* Playlists */}
            {data.playlists.length > 0 && (
              <section>
                <h4 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 flex items-center gap-1.5">
                  <Disc3 className="h-3.5 w-3.5" />
                  Playlists
                </h4>
                <ul className="space-y-1">
                  {data.playlists.map((pl) => (
                    <li key={pl.id} className="flex items-center gap-2.5 p-2 rounded-md hover:bg-muted/50 group">
                      <div className="h-9 w-9 rounded bg-muted shrink-0 flex items-center justify-center">
                        <Disc3 className="h-4 w-4 text-muted-foreground" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-sm truncate">{pl.name}</p>
                        <Badge variant="outline" className="text-[10px] px-1 py-0 h-3.5">Playlist</Badge>
                      </div>
                      <div className="opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-7 w-7"
                          onClick={() => play.mutate({ contextUri: pl.uri, deviceId: profile.default_spotify_device_id ?? undefined })}
                          disabled={play.isPending}
                          title="Abspielen"
                        >
                          <Play className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    </li>
                  ))}
                </ul>
              </section>
            )}
          </div>
        )}
      </ScrollArea>

      {/* Add to Playlist Dialog */}
      <Dialog open={!!addTarget} onOpenChange={(open) => !open && setAddTarget(null)}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>Zu Playlist hinzufügen</DialogTitle>
          </DialogHeader>
          {addTarget && (
            <div className="space-y-2">
              <p className="text-sm text-muted-foreground">
                Track: <span className="font-medium text-foreground">{addTarget.name}</span>
              </p>
              {playlists.length === 0 ? (
                <p className="text-sm text-muted-foreground py-4 text-center">Keine eigenen Playlists verfügbar.</p>
              ) : (
                <ul className="space-y-1 max-h-60 overflow-y-auto">
                  {playlists.map((pl) => (
                    <li key={pl.id}>
                      <button
                        type="button"
                        className="w-full text-left px-3 py-2 rounded-md hover:bg-muted text-sm flex items-center gap-2 transition-colors"
                        onClick={() => handleAddToPlaylist(pl.id)}
                        disabled={addTracks.isPending}
                      >
                        {addTracks.isPending ? (
                          <Loader2 className="h-4 w-4 animate-spin shrink-0" />
                        ) : (
                          <Plus className="h-4 w-4 shrink-0 text-muted-foreground" />
                        )}
                        {pl.name}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}

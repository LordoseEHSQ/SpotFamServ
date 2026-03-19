import { Play, Loader2, Music2, Clock, Disc3, AlertCircle, BookOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useSpotifyPlaylistTracks } from '@/hooks/useSpotifyPlaylists';
import { usePlaySpotify } from '@/hooks/useSpotifyPlayer';
import type { SpotifyPlaylistItem, SpotifyTrackItem } from '@/api/endpoints/spotify';
import type { FamilyProfileDto } from '@/api/endpoints/profiles';

function formatMs(ms: number): string {
  const total = Math.floor(ms / 1000);
  const m = Math.floor(total / 60);
  const s = total % 60;
  return `${m}:${s.toString().padStart(2, '0')}`;
}

interface PlaylistDetailProps {
  profile: FamilyProfileDto;
  playlist: SpotifyPlaylistItem;
  onAddTrackFromSearch?: (track: SpotifyTrackItem) => void;
}

export function PlaylistDetail({ profile, playlist, onAddTrackFromSearch: _ }: PlaylistDetailProps) {
  const { data, isLoading, error } = useSpotifyPlaylistTracks(profile.id, playlist.id);
  const play = usePlaySpotify(profile.id);
  const trackError = error as { status?: number; detail?: string } | null;

  const handlePlayPlaylist = () => {
    play.mutate({
      contextUri: playlist.uri,
      deviceId: profile.default_spotify_device_id ?? undefined,
    });
  };

  return (
    <div className="flex flex-col h-full">
      {/* Playlist Header */}
      <div className="px-4 py-3 border-b shrink-0">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <div className="flex items-center gap-2 mb-0.5">
              <Disc3 className="h-4 w-4 text-muted-foreground shrink-0" />
              <h3 className="font-semibold truncate">{playlist.name}</h3>
            </div>
            {data && (
              <p className="text-xs text-muted-foreground">{data.total} Tracks</p>
            )}
          </div>
          <Button
            size="sm"
            onClick={handlePlayPlaylist}
            disabled={play.isPending || profile.spotify_status !== 'connected'}
            title={profile.default_spotify_device_id ? undefined : 'Kein Standardlautsprecher zugeordnet'}
          >
            {play.isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Play className="h-4 w-4" />
            )}
            Abspielen
          </Button>
        </div>

        {!profile.default_spotify_device_id && (
          <p className="text-xs text-amber-700 mt-1.5 flex items-center gap-1">
            <span>⚠</span>
            Kein Standardlautsprecher zugeordnet – Wiedergabe startet auf aktivem Gerät.
          </p>
        )}
      </div>

      {/* Track List */}
      <ScrollArea className="flex-1">
        {isLoading ? (
          <div className="space-y-2 p-4">
            {Array.from({ length: 8 }).map((_, i) => (
              <Skeleton key={i} className="h-10 w-full" />
            ))}
          </div>
        ) : trackError ? (
          <div className="flex flex-col items-center justify-center py-12 px-6 text-center">
            {(trackError.status === 422 || trackError.status === 403) ? (
              <>
                <BookOpen className="h-10 w-10 mb-3 text-amber-500/60" />
                <p className="text-sm font-medium text-amber-700 dark:text-amber-400">Inhalt nicht über API verfügbar</p>
                <p className="text-xs mt-1.5 text-muted-foreground max-w-xs">
                  Diese Playlist enthält wahrscheinlich Podcast- oder Hörbuch-Inhalte. Spotify hat den API-Zugriff auf solche Inhalte eingeschränkt (seit Nov 2024). Die Playlist kann trotzdem über den „Abspielen"-Button gestartet werden.
                </p>
              </>
            ) : (
              <>
                <AlertCircle className="h-10 w-10 mb-3 text-destructive/60" />
                <p className="text-sm font-medium">Tracks konnten nicht geladen werden</p>
                <p className="text-xs mt-1 text-muted-foreground">{trackError.detail ?? 'Unbekannter Fehler'}</p>
              </>
            )}
          </div>
        ) : !data || data.items.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
            <Music2 className="h-10 w-10 mb-3 opacity-40" />
            <p className="text-sm">Keine Tracks in dieser Playlist</p>
            <p className="text-xs mt-1">Füge Tracks über die Suche hinzu</p>
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="w-8 text-center">#</TableHead>
                <TableHead>Titel</TableHead>
                <TableHead className="hidden sm:table-cell">Künstler</TableHead>
                <TableHead className="hidden md:table-cell">Album</TableHead>
                <TableHead className="w-16 text-right">
                  <Clock className="h-3.5 w-3.5 inline" />
                </TableHead>
                <TableHead className="w-10" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.items.map((track, idx) => (
                <TrackRow
                  key={track.id}
                  track={track}
                  index={idx + 1}
                  profileId={profile.id}
                  playlistUri={playlist.uri}
                  deviceId={profile.default_spotify_device_id ?? undefined}
                />
              ))}
            </TableBody>
          </Table>
        )}
      </ScrollArea>
    </div>
  );
}

function TrackRow({
  track,
  index,
  profileId,
  playlistUri,
  deviceId,
}: {
  track: SpotifyTrackItem;
  index: number;
  profileId: string;
  playlistUri: string;
  deviceId?: string;
}) {
  const play = usePlaySpotify(profileId);

  return (
    <TableRow className="group">
      <TableCell className="text-center text-muted-foreground text-xs w-8">{index}</TableCell>
      <TableCell>
        <div className="flex items-center gap-2 min-w-0">
          {track.album_cover_url ? (
            <img
              src={track.album_cover_url}
              alt=""
              className="h-8 w-8 rounded object-cover shrink-0"
            />
          ) : (
            <div className="h-8 w-8 rounded bg-muted shrink-0 flex items-center justify-center">
              <Music2 className="h-3.5 w-3.5 text-muted-foreground" />
            </div>
          )}
          <span className="font-medium text-sm truncate">{track.name}</span>
        </div>
      </TableCell>
      <TableCell className="hidden sm:table-cell text-sm text-muted-foreground truncate max-w-[120px]">
        {track.artists.join(', ')}
      </TableCell>
      <TableCell className="hidden md:table-cell text-sm text-muted-foreground truncate max-w-[100px]">
        {track.album_name}
      </TableCell>
      <TableCell className="text-right text-xs text-muted-foreground">
        {formatMs(track.duration_ms)}
      </TableCell>
      <TableCell>
        <Button
          variant="ghost"
          size="icon"
          className="h-7 w-7 opacity-0 group-hover:opacity-100 transition-opacity"
          onClick={() => play.mutate({ contextUri: playlistUri, deviceId })}
          disabled={play.isPending}
          title="Playlist ab diesem Track abspielen"
        >
          <Play className="h-3.5 w-3.5" />
        </Button>
      </TableCell>
    </TableRow>
  );
}

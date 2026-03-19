import { SkipBack, SkipForward, Play, Pause, Loader2, MonitorSpeaker, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateRelative } from '@/lib/utils';
import {
  useSpotifyPlayer,
  usePauseSpotify,
  useNextTrack,
  usePreviousTrack,
} from '@/hooks/useSpotifyPlayer';
import type { FamilyProfileDto } from '@/api/endpoints/profiles';

function formatMs(ms: number): string {
  const total = Math.floor(ms / 1000);
  const m = Math.floor(total / 60);
  const s = total % 60;
  return `${m}:${s.toString().padStart(2, '0')}`;
}

interface MiniPlayerProps {
  profile: FamilyProfileDto;
}

export function MiniPlayer({ profile }: MiniPlayerProps) {
  const { data, isLoading, error } = useSpotifyPlayer(profile.id);
  const pause = usePauseSpotify(profile.id);
  const next = useNextTrack(profile.id);
  const prev = usePreviousTrack(profile.id);

  const isConnected = profile.spotify_status === 'connected';

  if (!isConnected) {
    return (
      <div className="rounded-lg border bg-card p-4 space-y-2">
        <div className="flex items-center gap-2 text-sm font-medium">
          <MonitorSpeaker className="h-4 w-4 text-muted-foreground" />
          <span>Mini-Player</span>
        </div>
        <div className="rounded-md bg-amber-50 border border-amber-200 px-3 py-2.5 text-sm text-amber-800 flex items-start gap-2">
          <AlertCircle className="h-4 w-4 mt-0.5 shrink-0" />
          <span>Spotify nicht verbunden. Verbinde das Konto im Tab „Spotify".</span>
        </div>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="rounded-lg border bg-card p-4 space-y-3">
        <Skeleton className="h-4 w-28" />
        <Skeleton className="h-14 w-full rounded" />
        <Skeleton className="h-8 w-full" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="rounded-lg border bg-card p-4">
        <div className="flex items-center gap-2 text-sm font-medium mb-2">
          <MonitorSpeaker className="h-4 w-4 text-muted-foreground" />
          <span>Mini-Player</span>
        </div>
        <p className="text-sm text-destructive">Player-Status konnte nicht geladen werden.</p>
      </div>
    );
  }

  const state = data?.state;
  const isPlaying = state?.is_playing ?? false;
  const track = state?.current_track;

  return (
    <div className="rounded-lg border bg-card overflow-hidden">
      {/* Header */}
      <div className="px-4 py-3 border-b bg-muted/30 flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm font-medium">
          <MonitorSpeaker className="h-4 w-4 text-muted-foreground" />
          <span>Mini-Player</span>
        </div>
        <div className="flex items-center gap-1.5">
          {data?.playing ? (
            <span className="flex items-center gap-1 text-xs text-emerald-700">
              <span className="relative flex h-2 w-2">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-500 opacity-75" />
                <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
              </span>
              Live
            </span>
          ) : (
            <Badge variant="outline" className="text-xs">Pausiert</Badge>
          )}
        </div>
      </div>

      {/* Track Info */}
      <div className="p-4 space-y-3">
        {track ? (
          <div className="flex gap-3">
            {track.album_cover_url ? (
              <img
                src={track.album_cover_url}
                alt={track.album_name ?? 'Album'}
                className="h-14 w-14 rounded object-cover shrink-0 shadow-sm"
              />
            ) : (
              <div className="h-14 w-14 rounded bg-muted flex items-center justify-center shrink-0">
                <MonitorSpeaker className="h-6 w-6 text-muted-foreground" />
              </div>
            )}
            <div className="min-w-0 flex-1">
              <p className="font-medium text-sm truncate">{track.name}</p>
              <p className="text-xs text-muted-foreground truncate">{track.artists.join(', ')}</p>
              {track.album_name && (
                <p className="text-xs text-muted-foreground/70 truncate mt-0.5">{track.album_name}</p>
              )}
              {state && (
                <p className="text-xs text-muted-foreground mt-1">
                  {formatMs(state.progress_ms)} / {formatMs(track.duration_ms)}
                </p>
              )}
            </div>
          </div>
        ) : (
          <div className="flex items-center gap-3 text-sm text-muted-foreground py-2">
            <MonitorSpeaker className="h-8 w-8 text-muted-foreground/40" />
            <span>Keine aktive Wiedergabe</span>
          </div>
        )}

        {/* Controls */}
        <div className="flex items-center justify-center gap-2">
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            onClick={() => prev.mutate()}
            disabled={prev.isPending || !data?.playing}
          >
            {prev.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <SkipBack className="h-4 w-4" />}
          </Button>

          <Button
            variant="default"
            size="icon"
            className="h-9 w-9 rounded-full"
            onClick={() => {
              if (isPlaying) {
                pause.mutate(state?.device_id ?? undefined);
              }
            }}
            disabled={pause.isPending || !data?.playing}
          >
            {pause.isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : isPlaying ? (
              <Pause className="h-4 w-4" />
            ) : (
              <Play className="h-4 w-4" />
            )}
          </Button>

          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            onClick={() => next.mutate()}
            disabled={next.isPending || !data?.playing}
          >
            {next.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <SkipForward className="h-4 w-4" />}
          </Button>
        </div>

        {/* Device Info */}
        {state?.device_name && (
          <div className="border-t pt-2 mt-1">
            <p className="text-xs text-muted-foreground flex items-center gap-1.5">
              <MonitorSpeaker className="h-3 w-3" />
              <span className="truncate">{state.device_name}</span>
              {state.device_type && (
                <span className="text-muted-foreground/60">· {state.device_type}</span>
              )}
            </p>
          </div>
        )}

        {/* Governance Hinweise */}
        {!profile.default_device_name && (
          <div className="rounded-md bg-amber-50 border border-amber-200 px-2.5 py-2 text-xs text-amber-800 flex items-start gap-1.5">
            <AlertCircle className="h-3.5 w-3.5 mt-0.5 shrink-0" />
            <span>Kein Standardlautsprecher zugeordnet</span>
          </div>
        )}

        {profile.last_activity_at && (
          <p className="text-xs text-muted-foreground text-right">
            Letzte Aktivität {formatDateRelative(profile.last_activity_at)}
          </p>
        )}
      </div>
    </div>
  );
}

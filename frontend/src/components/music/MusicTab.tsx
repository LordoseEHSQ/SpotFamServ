import { useState } from 'react';
import { Music2, Search, AlertCircle, CheckCircle2, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { MiniPlayer } from './MiniPlayer';
import { PlaylistList } from './PlaylistList';
import { PlaylistDetail } from './PlaylistDetail';
import { SpotifySearch } from './SpotifySearch';
import type { FamilyProfileDto } from '@/api/endpoints/profiles';
import type { SpotifyPlaylistItem } from '@/api/endpoints/spotify';
import { useSpotifyPlaylists } from '@/hooks/useSpotifyPlaylists';
import { useSpotifyAppConfig } from '@/hooks/useSpotifyAppConfig';
import { api } from '@/api/client';
interface MusicTabProps {
  profile: FamilyProfileDto;
}

export function MusicTab({ profile }: MusicTabProps) {
  const [selectedPlaylist, setSelectedPlaylist] = useState<SpotifyPlaylistItem | null>(null);
  const [activeSection, setActiveSection] = useState<'playlists' | 'search'>('playlists');
  const { data: sysConfig } = useSpotifyAppConfig();
  const { data: playlistData } = useSpotifyPlaylists(profile.id, profile.spotify_status === 'connected');
  const playlists = playlistData?.items ?? [];

  const isConnected = profile.spotify_status === 'connected';
  const needsReauth = profile.spotify_status === 'reauth_required';

  const handleConnect = async () => {
    try {
      const res = await api.get<{ authorization_url: string }>(`/profiles/${profile.id}/spotify/authorization-url`);
      window.location.href = res.authorization_url;
    } catch {
      /* will show error state */
    }
  };

  // Guard: System-Config nicht vollständig
  if (sysConfig && !sysConfig.is_complete) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-center space-y-3 max-w-sm mx-auto">
        <AlertCircle className="h-10 w-10 text-amber-500" />
        <h3 className="font-semibold">Spotify nicht konfiguriert</h3>
        <p className="text-sm text-muted-foreground">
          Die globale Spotify-App-Konfiguration ist unvollständig.
          Trage Client ID, Client Secret und Redirect URI unter{' '}
          <strong>Systemeinstellungen → Spotify</strong> ein.
        </p>
      </div>
    );
  }

  // Guard: Spotify nicht verbunden
  if (!isConnected) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-center space-y-4 max-w-sm mx-auto">
        <Music2 className="h-10 w-10 text-muted-foreground/40" />
        <div>
          <h3 className="font-semibold mb-1">Spotify nicht verbunden</h3>
          <p className="text-sm text-muted-foreground">
            {needsReauth
              ? 'Die Spotify-Verbindung muss neu autorisiert werden. Bitte neu verbinden.'
              : 'Verbinde das Spotify-Konto, um den Musikbereich zu nutzen.'}
          </p>
        </div>
        <div className="flex gap-2 flex-wrap justify-center">
          <Button size="sm" onClick={handleConnect}>
            <ExternalLink className="h-4 w-4" />
            {needsReauth ? 'Neu verbinden' : 'Mit Spotify verbinden'}
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex h-[calc(100vh-280px)] min-h-[480px] gap-0 border rounded-lg overflow-hidden">
      {/* LEFT: Playlists */}
      <div className="w-56 border-r flex flex-col bg-muted/20 shrink-0">
        <PlaylistList
          profileId={profile.id}
          selectedId={selectedPlaylist?.id ?? null}
          onSelect={setSelectedPlaylist}
          spotifyConnected={isConnected}
        />
      </div>

      {/* CENTER: Playlist Detail or Search */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Tabs: Playlist-Detail / Suche */}
        <div className="border-b px-3 pt-2 pb-0 shrink-0">
          <div className="flex items-center gap-1">
            <button
              type="button"
              onClick={() => setActiveSection('playlists')}
              className={`px-3 py-1.5 text-sm rounded-t-md border border-b-0 -mb-px transition-colors ${
                activeSection === 'playlists'
                  ? 'bg-background border-border text-foreground font-medium'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              }`}
            >
              <Music2 className="h-3.5 w-3.5 inline mr-1.5" />
              {selectedPlaylist ? selectedPlaylist.name : 'Playlists'}
            </button>
            <button
              type="button"
              onClick={() => setActiveSection('search')}
              className={`px-3 py-1.5 text-sm rounded-t-md border border-b-0 -mb-px transition-colors ${
                activeSection === 'search'
                  ? 'bg-background border-border text-foreground font-medium'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              }`}
            >
              <Search className="h-3.5 w-3.5 inline mr-1.5" />
              Suche
            </button>
          </div>
        </div>

        <div className="flex-1 min-h-0 overflow-hidden">
          {activeSection === 'playlists' ? (
            selectedPlaylist ? (
              <PlaylistDetail
                profile={profile}
                playlist={selectedPlaylist}
              />
            ) : (
              <div className="flex flex-col items-center justify-center h-full text-muted-foreground">
                <Music2 className="h-10 w-10 mb-3 opacity-40" />
                <p className="text-sm">Playlist aus der Liste wählen</p>
              </div>
            )
          ) : (
            <SpotifySearch profile={profile} playlists={playlists} />
          )}
        </div>
      </div>

      {/* RIGHT: Mini-Player + Status */}
      <div className="w-60 border-l flex flex-col bg-muted/10 shrink-0">
        <div className="px-3 py-2.5 border-b text-xs font-semibold text-muted-foreground uppercase tracking-wider">
          Wiedergabe
        </div>
        <div className="flex-1 overflow-y-auto p-3 space-y-3">
          <MiniPlayer profile={profile} />

          <Separator />

          {/* Governance-Statusbereich */}
          <div className="space-y-1.5">
            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Status</p>
            <StatusRow
              label="Spotify"
              ok={isConnected}
              text={isConnected ? (profile.spotify_user_display_name ?? 'Verbunden') : 'Nicht verbunden'}
            />
            <StatusRow
              label="Lautsprecher"
              ok={!!profile.default_device_name}
              text={profile.default_device_name ?? 'Nicht zugeordnet'}
            />
            <StatusRow
              label="Setup"
              ok={profile.setup_complete}
              text={profile.setup_complete ? 'Vollständig' : `${profile.setup_percent}% abgeschlossen`}
            />
          </div>
        </div>
      </div>
    </div>
  );
}

function StatusRow({ label, ok, text }: { label: string; ok: boolean; text: string }) {
  return (
    <div className="flex items-center gap-1.5 text-xs">
      {ok ? (
        <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 shrink-0" />
      ) : (
        <AlertCircle className="h-3.5 w-3.5 text-amber-500 shrink-0" />
      )}
      <span className="text-muted-foreground">{label}:</span>
      <span className={`truncate ${ok ? '' : 'text-amber-700'}`}>{text}</span>
    </div>
  );
}

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { spotifyMusicApi } from '@/api/endpoints/spotify';

export const playlistsKey = (profileId: string) => ['spotify', 'playlists', profileId] as const;
export const playlistTracksKey = (profileId: string, playlistId: string) =>
  ['spotify', 'playlist-tracks', profileId, playlistId] as const;

export function useSpotifyPlaylists(profileId: string, enabled = true) {
  return useQuery({
    queryKey: playlistsKey(profileId),
    queryFn: () => spotifyMusicApi.getPlaylists(profileId),
    enabled: enabled && !!profileId,
    staleTime: 30_000,
  });
}

export function useSpotifyPlaylistTracks(profileId: string, playlistId: string | null) {
  return useQuery({
    queryKey: playlistTracksKey(profileId, playlistId ?? ''),
    queryFn: () => spotifyMusicApi.getPlaylistTracks(profileId, playlistId!),
    enabled: !!profileId && !!playlistId,
    staleTime: 20_000,
    // Don't retry on 4xx – these are Spotify API restrictions, not transient errors
    retry: (failureCount, error: unknown) => {
      const status = (error as { status?: number })?.status ?? 0;
      if (status >= 400 && status < 500) return false;
      return failureCount < 2;
    },
  });
}

export function useCreateSpotifyPlaylist(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ name, description }: { name: string; description?: string }) =>
      spotifyMusicApi.createPlaylist(profileId, name, description),
    onSuccess: () => qc.invalidateQueries({ queryKey: playlistsKey(profileId) }),
  });
}

export function useAddTracksToPlaylist(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ playlistId, uris }: { playlistId: string; uris: string[] }) =>
      spotifyMusicApi.addTracks(profileId, playlistId, uris),
    onSuccess: (_data, vars) => {
      qc.invalidateQueries({ queryKey: playlistTracksKey(profileId, vars.playlistId) });
    },
  });
}

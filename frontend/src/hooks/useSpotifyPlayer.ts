import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { spotifyMusicApi } from '@/api/endpoints/spotify';

export const playerKey = (profileId: string) => ['spotify', 'player', profileId] as const;

export function useSpotifyPlayer(profileId: string, enabled = true) {
  return useQuery({
    queryKey: playerKey(profileId),
    queryFn: () => spotifyMusicApi.getPlayer(profileId),
    enabled: enabled && !!profileId,
    refetchInterval: 5_000,
    refetchIntervalInBackground: false,
    staleTime: 3_000,
  });
}

export function usePlaySpotify(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ contextUri, deviceId }: { contextUri: string; deviceId?: string }) =>
      spotifyMusicApi.play(profileId, contextUri, deviceId),
    onSuccess: () => {
      setTimeout(() => qc.invalidateQueries({ queryKey: playerKey(profileId) }), 500);
    },
  });
}

export function usePauseSpotify(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (deviceId?: string) => spotifyMusicApi.pause(profileId, deviceId),
    onSuccess: () => {
      setTimeout(() => qc.invalidateQueries({ queryKey: playerKey(profileId) }), 500);
    },
  });
}

export function useNextTrack(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => spotifyMusicApi.next(profileId),
    onSuccess: () => {
      setTimeout(() => qc.invalidateQueries({ queryKey: playerKey(profileId) }), 800);
    },
  });
}

export function usePreviousTrack(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => spotifyMusicApi.previous(profileId),
    onSuccess: () => {
      setTimeout(() => qc.invalidateQueries({ queryKey: playerKey(profileId) }), 800);
    },
  });
}

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { spotifySystemApi, type SaveSpotifyAppConfigRequest } from '@/api/endpoints/spotify';

export const SPOTIFY_CONFIG_KEY = ['spotify', 'system-config'] as const;

export function useSpotifyAppConfig() {
  return useQuery({
    queryKey: SPOTIFY_CONFIG_KEY,
    queryFn: () => spotifySystemApi.getConfig(),
    staleTime: 60_000,
  });
}

export function useSaveSpotifyAppConfig() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: SaveSpotifyAppConfigRequest) => spotifySystemApi.saveConfig(data),
    onSuccess: () => qc.invalidateQueries({ queryKey: SPOTIFY_CONFIG_KEY }),
  });
}

export function useValidateSpotifyAppConfig() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => spotifySystemApi.validate(),
    onSuccess: () => qc.invalidateQueries({ queryKey: SPOTIFY_CONFIG_KEY }),
  });
}

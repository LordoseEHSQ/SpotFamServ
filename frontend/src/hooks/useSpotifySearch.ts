import { useQuery } from '@tanstack/react-query';
import { spotifyMusicApi } from '@/api/endpoints/spotify';

export const searchKey = (profileId: string, q: string, type: string) =>
  ['spotify', 'search', profileId, q, type] as const;

export function useSpotifySearch(profileId: string, q: string, type = 'playlist,track') {
  return useQuery({
    queryKey: searchKey(profileId, q, type),
    queryFn: () => spotifyMusicApi.search(profileId, q, type),
    enabled: !!profileId && q.trim().length >= 2,
    staleTime: 60_000,
  });
}

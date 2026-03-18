import { useQuery } from '@tanstack/react-query';
import { activityApi, type ActivitySeverity } from '@/api/endpoints/activity';

export const activityKeys = {
  all: ['activity'] as const,
  list: (params?: { profile_id?: string; severity?: ActivitySeverity }) =>
    [...activityKeys.all, 'list', params] as const,
};

export function useActivity(params?: { profile_id?: string; limit?: number; severity?: ActivitySeverity }) {
  return useQuery({
    queryKey: activityKeys.list({ profile_id: params?.profile_id, severity: params?.severity }),
    queryFn: () => activityApi.list(params),
    staleTime: 15 * 1000,
    refetchInterval: 30 * 1000,
  });
}

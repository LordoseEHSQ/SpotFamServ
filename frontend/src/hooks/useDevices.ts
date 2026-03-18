import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { devicesApi, type AssignDeviceRequest } from '@/api/endpoints/devices';

export const deviceKeys = {
  all: ['devices'] as const,
  list: () => [...deviceKeys.all, 'list'] as const,
  detail: (id: string) => [...deviceKeys.all, 'detail', id] as const,
  discoveryRuns: () => [...deviceKeys.all, 'discovery-runs'] as const,
  latestRun: () => [...deviceKeys.all, 'discovery-runs', 'latest'] as const,
};

export function useDevices() {
  return useQuery({
    queryKey: deviceKeys.list(),
    queryFn: devicesApi.list,
    staleTime: 30 * 1000,
  });
}

export function useDevice(id: string) {
  return useQuery({
    queryKey: deviceKeys.detail(id),
    queryFn: () => devicesApi.get(id),
    enabled: !!id,
  });
}

export function useLatestDiscoveryRun() {
  return useQuery({
    queryKey: deviceKeys.latestRun(),
    queryFn: devicesApi.latestDiscoveryRun,
    staleTime: 10 * 1000,
    refetchInterval: 15 * 1000,
  });
}

export function useDiscoveryRuns(limit = 10) {
  return useQuery({
    queryKey: [...deviceKeys.discoveryRuns(), limit],
    queryFn: () => devicesApi.discoveryRuns(limit),
  });
}

export function useTriggerDiscovery() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (profileId?: string) => devicesApi.discover(profileId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: deviceKeys.list() });
      queryClient.invalidateQueries({ queryKey: deviceKeys.discoveryRuns() });
    },
  });
}

export function useAssignDevice() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: AssignDeviceRequest }) =>
      devicesApi.assign(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: deviceKeys.list() });
    },
  });
}

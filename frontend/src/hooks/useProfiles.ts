import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { profilesApi } from '../api/endpoints/profiles';

export const profileKeys = {
  all: ['profiles'] as const,
  list: () => [...profileKeys.all, 'list'] as const,
  detail: (id: string) => [...profileKeys.all, id] as const,
};

export function useProfiles() {
  return useQuery({
    queryKey: profileKeys.list(),
    queryFn: () => profilesApi.list(),
  });
}

export function useProfile(id: string | undefined | null, options?: { enabled: boolean }) {
  return useQuery({
    queryKey: profileKeys.detail(id ?? ''),
    queryFn: () => profilesApi.get(id!),
    enabled: (options?.enabled ?? true) && !!id,
  });
}

export function useCreateProfile() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: { name: string; description?: string | null }) => profilesApi.create(data),
    onSuccess: () => qc.invalidateQueries({ queryKey: profileKeys.all }),
  });
}

export function useUpdateProfile(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: { name: string; description?: string | null }) =>
      profilesApi.update(id, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: profileKeys.all });
      qc.invalidateQueries({ queryKey: profileKeys.detail(id) });
    },
  });
}

export function useDeleteProfile() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => profilesApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: profileKeys.all }),
  });
}

export function useSetDefaultDevice(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (device: { deviceId: string; deviceName?: string | null } | null) =>
      device === null
        ? profilesApi.clearDefaultDevice(id)
        : profilesApi.setDefaultDevice(id, device.deviceId, device.deviceName),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: profileKeys.all });
      qc.invalidateQueries({ queryKey: profileKeys.detail(id) });
    },
  });
}

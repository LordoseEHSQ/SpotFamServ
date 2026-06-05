import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { systemConfigApi, type SaveSystemConfigurationRequest } from '@/api/endpoints/system';

export const SYSTEM_CONFIG_KEY = ['system', 'configuration'] as const;

export function useSystemConfiguration() {
  return useQuery({
    queryKey: SYSTEM_CONFIG_KEY,
    queryFn: () => systemConfigApi.get(),
    staleTime: 60_000,
  });
}

export function useSaveSystemConfiguration() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: SaveSystemConfigurationRequest) => systemConfigApi.save(data),
    onSuccess: () => qc.invalidateQueries({ queryKey: SYSTEM_CONFIG_KEY }),
  });
}

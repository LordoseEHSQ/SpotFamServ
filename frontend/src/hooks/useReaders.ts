import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { readersApi } from '@/api/endpoints/readers';

export const readerKeys = {
  all: ['readers'] as const,
  list: () => [...readerKeys.all, 'list'] as const,
};

export function useReaders() {
  return useQuery({
    queryKey: readerKeys.list(),
    queryFn: readersApi.list,
    staleTime: 15 * 1000,
  });
}

export function useSetReaderBox() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ readerId, deviceId, deviceName }: { readerId: string; deviceId: string; deviceName?: string | null }) =>
      readersApi.setDefaultDevice(readerId, deviceId, deviceName),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: readerKeys.list() }),
  });
}

export function useClearReaderBox() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (readerId: string) => readersApi.clearDefaultDevice(readerId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: readerKeys.list() }),
  });
}

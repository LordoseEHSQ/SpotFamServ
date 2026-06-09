import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { readersApi } from '@/api/endpoints/readers';

export const readerKeys = {
  all: ['readers'] as const,
  list: () => [...readerKeys.all, 'list'] as const,
  claim: (claimCode: string) => [...readerKeys.all, 'claim', claimCode] as const,
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

export function useCreateReaderClaim() {
  return useMutation({
    mutationFn: readersApi.createClaim,
  });
}

export function useReaderClaimStatus(claimCode: string | null) {
  return useQuery({
    queryKey: readerKeys.claim(claimCode ?? ''),
    queryFn: () => readersApi.claimStatus(claimCode ?? ''),
    enabled: claimCode !== null,
    refetchInterval: (query) => (query.state.data?.status === 'pending' ? 2000 : false),
    staleTime: 1000,
  });
}

export function useClearReaderBox() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (readerId: string) => readersApi.clearDefaultDevice(readerId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: readerKeys.list() }),
  });
}

export function useRotateReaderApiKey() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (readerId: string) => readersApi.rotateApiKey(readerId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: readerKeys.all }),
  });
}

export function useRevokeReaderApiKey() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (readerId: string) => readersApi.revokeApiKey(readerId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: readerKeys.all }),
  });
}

export function useDeleteReader() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (readerId: string) => readersApi.deleteReader(readerId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: readerKeys.all });
      toast.success('Reader gelöscht.');
    },
    onError: () => {
      toast.error('Reader konnte nicht gelöscht werden.');
    },
  });
}

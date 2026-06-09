import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { audioExtractorApi, type ExtractRequest } from '@/api/endpoints/audioExtractor';

export const audioExtractorKeys = {
  all: ['audio-extractor'] as const,
  config: () => [...audioExtractorKeys.all, 'config'] as const,
  files: () => [...audioExtractorKeys.all, 'files'] as const,
  jobs: () => [...audioExtractorKeys.all, 'jobs'] as const,
};

export function useAudioExtractorConfig() {
  return useQuery({
    queryKey: audioExtractorKeys.config(),
    queryFn: audioExtractorApi.config,
    staleTime: 5 * 60 * 1000,
  });
}

export function useAudioFiles() {
  return useQuery({
    queryKey: audioExtractorKeys.files(),
    queryFn: audioExtractorApi.files,
    staleTime: 10 * 1000,
  });
}

/**
 * Polls the job list. While any job is still pending/running it refetches every 2s, so the
 * UI tracks the background worker without a manual reload; once everything is terminal it
 * stops polling (refetchInterval → false) to avoid pointless traffic.
 */
export function useAudioJobs() {
  return useQuery({
    queryKey: audioExtractorKeys.jobs(),
    queryFn: audioExtractorApi.jobs,
    refetchInterval: (query) => {
      const items = query.state.data?.items ?? [];
      const active = items.some((j) => j.status === 'pending' || j.status === 'running');
      return active ? 2000 : false;
    },
  });
}

export function useExtractAudio() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (body: ExtractRequest) => audioExtractorApi.extract(body),
    onSuccess: () => {
      // A new pending job exists – refresh the job list so polling picks it up immediately.
      queryClient.invalidateQueries({ queryKey: audioExtractorKeys.jobs() });
    },
  });
}

export function useCancelAudioJob() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => audioExtractorApi.cancelJob(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: audioExtractorKeys.jobs() });
    },
  });
}

export function useDismissAudioJob() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => audioExtractorApi.dismissJob(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: audioExtractorKeys.jobs() });
    },
    onError: () => {
      toast.error('Job konnte nicht entfernt werden.');
    },
  });
}

export function useDeleteAudioFile() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (name: string) => audioExtractorApi.remove(name),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: audioExtractorKeys.files() });
    },
  });
}

export function useUpdateEngine() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => audioExtractorApi.update(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: audioExtractorKeys.config() });
    },
  });
}

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { audioExtractorApi, type ExtractRequest } from '@/api/endpoints/audioExtractor';

export const audioExtractorKeys = {
  all: ['audio-extractor'] as const,
  config: () => [...audioExtractorKeys.all, 'config'] as const,
  files: () => [...audioExtractorKeys.all, 'files'] as const,
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

export function useExtractAudio() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (body: ExtractRequest) => audioExtractorApi.extract(body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: audioExtractorKeys.files() });
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

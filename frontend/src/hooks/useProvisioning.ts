import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { provisioningApi, type CreateFlashJobRequest, type UploadArtifactRequest } from '@/api/endpoints/provisioning';

// ─── Query Keys ───────────────────────────────────────────────────────────────

export const provisioningKeys = {
  all: ['provisioning'] as const,
  devices: () => [...provisioningKeys.all, 'devices'] as const,
  artifacts: () => [...provisioningKeys.all, 'artifacts'] as const,
  job: (jobId: string) => [...provisioningKeys.all, 'jobs', jobId] as const,
};

// ─── Hooks ────────────────────────────────────────────────────────────────────

/** Live-Liste erkannter Geräte – alle 3 s aktualisiert. */
export function useDetectedDevices() {
  return useQuery({
    queryKey: provisioningKeys.devices(),
    queryFn: provisioningApi.listDevices,
    refetchInterval: 3000,
    staleTime: 2000,
  });
}

/** Liste verfügbarer Firmware-Artefakte – selten geändert, kein Polling nötig. */
export function useArtifacts() {
  return useQuery({
    queryKey: provisioningKeys.artifacts(),
    queryFn: provisioningApi.listArtifacts,
    staleTime: 60 * 1000,
  });
}

/** Startet einen neuen Flash-Job; invalidiert anschließend die Geräteliste. */
export function useCreateFlashJob() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (body: CreateFlashJobRequest) => provisioningApi.createJob(body),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: provisioningKeys.devices() });
    },
  });
}

/** Lädt ein Firmware-Artefakt hoch; invalidiert anschließend die Artefakt-Liste. */
export function useUploadArtifact() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (req: UploadArtifactRequest) => provisioningApi.uploadArtifact(req),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: provisioningKeys.artifacts() });
    },
  });
}

/** Polling-Hook für einen einzelnen Job – hält an, wenn der Job abgeschlossen ist. */
export function useFlashJob(jobId: string | null) {
  return useQuery({
    queryKey: provisioningKeys.job(jobId ?? ''),
    queryFn: () => provisioningApi.getJob(jobId ?? ''),
    enabled: jobId !== null,
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status === 'pending' || status === 'running' ? 2000 : false;
    },
    staleTime: 1000,
  });
}

import { api } from '../client';

// ─── Typen ────────────────────────────────────────────────────────────────────

export interface LatestJobSummary {
  jobId: string;
  status: 'pending' | 'running' | 'success' | 'failed';
  progress: number;
}

export interface DetectedDevice {
  id: string;
  port: string;
  chip: string;
  chipDescription: string;
  mac: string;
  flashSize: number;
  status: 'idle' | 'flashing';
  firstSeenAt: string;
  lastSeenAt: string;
  latestJob: LatestJobSummary | null;
}

export interface FlashArtifact {
  id: string;
  board: string;
  channel: string;
  version: string;
  filename: string;
  expectedChip: string;
  sha256: string;
  sizeBytes: number;
  createdAt: string;
}

export interface FlashJob {
  jobId: string;
  deviceId: string;
  artifactId: string;
  status: 'pending' | 'running' | 'success' | 'failed';
  progress: number;
  message: string | null;
  updatedAt: string;
}

export interface CreateFlashJobRequest {
  deviceId: string;
  artifactId: string;
}

export interface DeviceListResponse {
  items: DetectedDevice[];
}

export interface ArtifactListResponse {
  items: FlashArtifact[];
}

// ─── API-Client-Funktionen ────────────────────────────────────────────────────

export const provisioningApi = {
  listDevices: () =>
    api.get<DeviceListResponse>('/provisioning/devices'),

  listArtifacts: () =>
    api.get<ArtifactListResponse>('/provisioning/artifacts'),

  createJob: (body: CreateFlashJobRequest) =>
    api.post<FlashJob>('/provisioning/jobs', body),

  getJob: (jobId: string) =>
    api.get<FlashJob>(`/provisioning/jobs/${encodeURIComponent(jobId)}`),
};

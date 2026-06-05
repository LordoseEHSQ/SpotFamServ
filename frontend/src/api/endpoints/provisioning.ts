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
  // Backend/Agent liefern eine vorformatierte Zeichenkette (z. B. "4MB"),
  // KEINE Byte-Zahl. Nicht durch formatBytes() jagen (ergaebe "4MB B"/NaN).
  flashSize: string;
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

export interface UploadArtifactRequest {
  file: File;
  board: string;
  channel: string;
  version: string;
  expectedChip: string;
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

  /**
   * Lädt eine Firmware-Datei hoch. Nutzt multipart/form-data; Content-Type wird
   * vom Browser gesetzt (mit boundary). api.upload setzt kein JSON-Content-Type.
   */
  uploadArtifact: ({ file, board, channel, version, expectedChip }: UploadArtifactRequest) => {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('board', board);
    fd.append('channel', channel);
    fd.append('version', version);
    fd.append('expectedChip', expectedChip);
    return api.upload<FlashArtifact>('/provisioning/artifacts', fd);
  },
};

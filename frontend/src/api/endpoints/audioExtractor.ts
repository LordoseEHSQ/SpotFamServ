import { api } from '../client';

export interface AudioFormatOption {
  value: string;
  supports_bitrate: boolean;
}

export interface AudioExtractorConfig {
  formats: AudioFormatOption[];
  bitrates_kbps: number[];
  default_bitrate_kbps: number;
  max_duration_seconds: number;
  engine_version: string | null;
}

export interface StoredAudioFileDto {
  name: string;
  size_bytes: number;
  created_at: string;
  mime_type: string;
  download_url: string;
}

export interface AudioFileListResponse {
  items: StoredAudioFileDto[];
  total_size_bytes: number;
}

export interface ExtractRequest {
  url: string;
  format: string;
  bitrate_kbps?: number;
}

export type AudioJobStatus = 'pending' | 'running' | 'done' | 'failed' | 'canceled';

export interface AudioJobDto {
  id: string;
  url: string;
  format: string;
  bitrate_kbps: number | null;
  status: AudioJobStatus;
  progress: number;
  error: string | null;
  result_file: string | null;
  download_url: string | null;
  created_at: string;
  updated_at: string;
}

export interface AudioJobListResponse {
  items: AudioJobDto[];
}

export const audioExtractorApi = {
  config: () => api.get<AudioExtractorConfig>('/audio-extractor/config'),
  files: () => api.get<AudioFileListResponse>('/audio-extractor/files'),
  // Async (D-032): POST /extract enqueues and returns 202 + the pending job.
  extract: (body: ExtractRequest) =>
    api.post<AudioJobDto>('/audio-extractor/extract', body),
  jobs: () => api.get<AudioJobListResponse>('/audio-extractor/jobs'),
  cancelJob: (id: string) =>
    api.delete<AudioJobDto>(`/audio-extractor/jobs/${encodeURIComponent(id)}`),
  remove: (name: string) =>
    api.delete(`/audio-extractor/files/${encodeURIComponent(name)}`),
  update: () => api.post<{ engine_version: string }>('/audio-extractor/update', {}),
};

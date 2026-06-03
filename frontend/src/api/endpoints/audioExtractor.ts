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

export const audioExtractorApi = {
  config: () => api.get<AudioExtractorConfig>('/audio-extractor/config'),
  files: () => api.get<AudioFileListResponse>('/audio-extractor/files'),
  extract: (body: ExtractRequest) =>
    api.post<StoredAudioFileDto>('/audio-extractor/extract', body),
  remove: (name: string) =>
    api.delete(`/audio-extractor/files/${encodeURIComponent(name)}`),
  update: () => api.post<{ engine_version: string }>('/audio-extractor/update', {}),
};

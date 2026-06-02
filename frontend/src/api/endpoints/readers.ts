import { api } from '../client';

export interface ReaderDto {
  id: string | null;
  reader_id: string;
  name: string | null;
  default_spotify_device_id: string | null;
  default_device_name: string | null;
}

export interface ReaderListResponse {
  items: ReaderDto[];
}

export const readersApi = {
  list: () => api.get<ReaderListResponse>('/readers'),
  setDefaultDevice: (readerId: string, device_id: string, device_name?: string | null) =>
    api.put<ReaderDto>(`/readers/${encodeURIComponent(readerId)}/default-device`, {
      device_id,
      device_name: device_name ?? null,
    }),
  clearDefaultDevice: (readerId: string) =>
    api.delete<ReaderDto>(`/readers/${encodeURIComponent(readerId)}/default-device`),
};

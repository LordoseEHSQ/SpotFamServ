import { api } from '../client';

export interface ReaderDto {
  id: string | null;
  reader_id: string;
  name: string | null;
  has_api_key: boolean;
  default_spotify_device_id: string | null;
  default_device_name: string | null;
}

export interface ReaderListResponse {
  items: ReaderDto[];
}

export interface CreateReaderClaimRequest {
  reader_name?: string | null;
  fw_channel?: string | null;
}

export interface ReaderClaimResponse {
  claim_code: string;
  expires_at: string;
  backend_url: string;
  fw_channel: string;
}

export interface ReaderClaimStatusResponse {
  status: 'pending' | 'claimed' | 'expired';
  expires_at: string;
  reader_id: string | null;
  fw_channel: string;
}

export const readersApi = {
  list: () => api.get<ReaderListResponse>('/readers'),
  createClaim: (body: CreateReaderClaimRequest) =>
    api.post<ReaderClaimResponse>('/readers/claims', body),
  claimStatus: (claimCode: string) =>
    api.get<ReaderClaimStatusResponse>(`/readers/claims/${encodeURIComponent(claimCode)}`),
  setDefaultDevice: (readerId: string, device_id: string, device_name?: string | null) =>
    api.put<ReaderDto>(`/readers/${encodeURIComponent(readerId)}/default-device`, {
      device_id,
      device_name: device_name ?? null,
    }),
  clearDefaultDevice: (readerId: string) =>
    api.delete<ReaderDto>(`/readers/${encodeURIComponent(readerId)}/default-device`),
};

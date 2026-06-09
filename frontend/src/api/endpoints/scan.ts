import { api } from '../client';

export interface ScanEventDto {
  id: string;
  card_uid_raw: string;
  outcome: string;
  reader_device_id: string | null;
  reader_id: string | null;
  message: string | null;
  created_at: string;
}

export interface ScanEventsResponse {
  items: ScanEventDto[];
}

export const scanApi = {
  listEvents: (params?: { profile_id?: string; reader_device_id?: string; limit?: number; offset?: number }) => {
    const sp = new URLSearchParams();
    if (params?.profile_id) sp.set('profile_id', params.profile_id);
    if (params?.reader_device_id) sp.set('reader_device_id', params.reader_device_id);
    if (params?.limit != null) sp.set('limit', String(params.limit));
    if (params?.offset != null) sp.set('offset', String(params.offset));
    const q = sp.toString();
    return api.get<ScanEventsResponse>(`/readers/scan-events${q ? `?${q}` : ''}`);
  },
};

import { api } from '../client';

export type AssignmentMode = 'unassigned' | 'assigned' | 'reserved' | 'locked' | 'shared';
export type DiscoveryStatus = 'available' | 'unavailable' | 'conflict' | 'unknown';

export interface SpotifyDeviceDto {
  id: string;
  spotify_device_id: string;
  spotify_device_name: string;
  device_type: string | null;
  is_available: boolean;
  last_seen_at: string | null;
  assigned_family_profile_id: string | null;
  assigned_profile_name: string | null;
  assignment_mode: AssignmentMode;
  assignment_updated_at: string | null;
  assignment_note: string | null;
  discovery_status: DiscoveryStatus;
  created_at: string;
  updated_at: string;
}

export interface DeviceListResponse {
  items: SpotifyDeviceDto[];
  total: number;
}

export interface DeviceDiscoveryRunDto {
  id: string;
  started_at: string;
  finished_at: string | null;
  scope: 'global' | 'profile';
  scope_profile_id: string | null;
  result_status: 'running' | 'success' | 'failed' | 'no_devices' | 'partial' | null;
  devices_found_count: number;
  devices_available_count: number;
  devices_new_count: number;
  error_message: string | null;
}

export interface AssignDeviceRequest {
  family_profile_id: string | null;
  assignment_mode: AssignmentMode;
  assignment_note?: string;
  force?: boolean;
}

export const devicesApi = {
  list: () => api.get<DeviceListResponse>('/devices'),
  get: (id: string) => api.get<SpotifyDeviceDto>(`/devices/${id}`),
  assign: (id: string, body: AssignDeviceRequest) =>
    api.put<SpotifyDeviceDto>(`/devices/${id}/assign`, body),
  discover: (profileId?: string) =>
    api.post<DeviceDiscoveryRunDto>('/devices/discover', { profile_id: profileId ?? null }),
  latestDiscoveryRun: () => api.get<DeviceDiscoveryRunDto | null>('/devices/discovery-runs/latest'),
  discoveryRuns: (limit = 10) =>
    api.get<{ items: DeviceDiscoveryRunDto[] }>(`/devices/discovery-runs?limit=${limit}`),
};

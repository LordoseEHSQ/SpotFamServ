import { api } from '../client';

export type ProfileStatus = 'active' | 'inactive';

export interface FamilyProfileDto {
  id: string;
  name: string;
  description: string | null;
  status: ProfileStatus;
  default_spotify_device_id: string | null;
  default_device_name: string | null;
  spotify_status: 'connected' | 'expired' | 'not_connected';
  spotify_user_display_name: string | null;
  setup_complete: boolean;
  setup_percent: number;
  last_activity_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface ProfileListResponse {
  items: FamilyProfileDto[];
}

export const profilesApi = {
  list: () => api.get<ProfileListResponse>('/profiles'),
  get: (id: string) => api.get<FamilyProfileDto>(`/profiles/${id}`),
  create: (data: { name: string; description?: string | null }) =>
    api.post<FamilyProfileDto>('/profiles', data),
  update: (id: string, data: { name: string; description?: string | null; status?: ProfileStatus }) =>
    api.put<FamilyProfileDto>(`/profiles/${id}`, data),
  delete: (id: string) => api.delete(`/profiles/${id}`),
};

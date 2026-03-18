import { api } from '../client';

export type ActivitySeverity = 'debug' | 'info' | 'warning' | 'error' | 'critical';
export type ActivityType =
  | 'rfid_scan'
  | 'playback_started'
  | 'playback_blocked'
  | 'playback_failed'
  | 'spotify_connected'
  | 'spotify_disconnected'
  | 'spotify_validated'
  | 'spotify_token_refreshed'
  | 'device_assigned'
  | 'device_unassigned'
  | 'device_discovered'
  | 'device_conflict'
  | 'device_not_available'
  | 'rule_limit_reached'
  | 'setup_completed'
  | 'system';

export interface ActivityLogEntryDto {
  id: string;
  family_profile_id: string | null;
  profile_name: string | null;
  related_entity_type: string | null;
  related_entity_id: string | null;
  activity_type: ActivityType;
  severity: ActivitySeverity;
  message: string;
  details: Record<string, unknown> | null;
  occurred_at: string;
}

export interface ActivityLogResponse {
  items: ActivityLogEntryDto[];
  total: number;
}

export const activityApi = {
  list: (params?: { profile_id?: string; limit?: number; offset?: number; severity?: ActivitySeverity }) => {
    const qs = new URLSearchParams();
    if (params?.profile_id) qs.set('profile_id', params.profile_id);
    if (params?.limit) qs.set('limit', String(params.limit));
    if (params?.offset) qs.set('offset', String(params.offset));
    if (params?.severity) qs.set('severity', params.severity);
    return api.get<ActivityLogResponse>(`/activity-log?${qs}`);
  },
};

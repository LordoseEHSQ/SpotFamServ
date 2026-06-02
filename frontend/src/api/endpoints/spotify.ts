import { api } from '../client';

// ─── Legacy compatibility types (used in setup-wizard) ─────────────────────

export type SpotifyDeviceDto = {
  id: string;
  name: string;
  type: string;
  is_active: boolean;
};

export type SpotifyPlaylistDto = {
  id: string;
  name: string;
  uri: string;
  owner_id: string | null;
};

export const spotifyApi = {
  getStatus: (profileId: string) =>
    api.get<{ status: string; link_id: string | null }>(`/profiles/${profileId}/spotify/status`),
  validate: (profileId: string) =>
    api.post<{ valid: boolean; spotify_user_id: string | null; display_name: string | null }>(
      `/profiles/${profileId}/spotify/validate`,
      {},
    ),
  getPlaylists: (profileId: string) =>
    api.get<{ items: SpotifyPlaylistItem[] }>(`/profiles/${profileId}/spotify/playlists`),
  getDevices: (profileId: string) =>
    api.get<{ items: SpotifyDeviceDto[] }>(`/profiles/${profileId}/spotify/devices`),
  listPlaylistReferences: (profileId: string) =>
    api.get<{ items: Array<{ id: string; name: string; spotify_playlist_id: string; owner_id: string | null }> }>(
      `/profiles/${profileId}/spotify/playlist-references`,
    ),
  createPlaylistReference: (
    profileId: string,
    data: { spotify_playlist_id: string; name: string; owner_id?: string | null },
  ) =>
    api.post<{ id: string; name: string; spotify_playlist_id: string; owner_id: string | null }>(
      `/profiles/${profileId}/spotify/playlist-references`,
      data,
    ),
  startPlayback: (profileId: string, data: { context_uri: string; device_id?: string }) =>
    api.post(`/profiles/${profileId}/spotify/playback/start`, data),
  getAuthorizationUrl: (profileId: string) =>
    api.get<{ authorization_url: string; state: string; redirect_uri: string }>(
      `/profiles/${profileId}/spotify/authorization-url`,
    ),
};

// ─── System Config ─────────────────────────────────────────────────────────

export type SpotifyConfigStatus = 'unconfigured' | 'configured' | 'validated' | 'error';

export interface SpotifyAppConfigDto {
  source: 'db' | 'env';
  config_status: SpotifyConfigStatus;
  is_complete: boolean;
  spotify_client_id: string | null;
  has_client_secret: boolean;
  redirect_uri: string | null;
  scope_defaults: string | null;
  last_check_at: string | null;
  last_check_note: string | null;
  updated_at: string;
}

export interface SaveSpotifyAppConfigRequest {
  spotify_client_id?: string;
  spotify_client_secret?: string;
  redirect_uri?: string;
  scope_defaults?: string;
}

// ─── Playlists & Tracks ────────────────────────────────────────────────────

export interface SpotifyPlaylistItem {
  id: string;
  name: string;
  uri: string;
  owner_id: string | null;
}

export interface SpotifyPlaylistsResponse {
  items: SpotifyPlaylistItem[];
}

export interface SpotifyTrackItem {
  id: string;
  name: string;
  uri: string;
  artists: string[];
  album_name: string | null;
  album_cover_url: string | null;
  duration_ms: number;
}

export interface SpotifyPlaylistTracksResponse {
  items: SpotifyTrackItem[];
  total: number;
  offset: number;
  limit: number;
}

// ─── Search ────────────────────────────────────────────────────────────────

export interface SpotifySearchPlaylistItem {
  id: string;
  name: string;
  uri: string;
  type: 'playlist';
}

export interface SpotifySearchTrackItem {
  id: string;
  name: string;
  uri: string;
  artists: string[];
  album_name: string | null;
  album_cover_url: string | null;
  duration_ms: number;
  type: 'track';
}

export interface SpotifySearchResponse {
  playlists: SpotifySearchPlaylistItem[];
  tracks: SpotifySearchTrackItem[];
}

// ─── Player ────────────────────────────────────────────────────────────────

export interface SpotifyCurrentTrack {
  id: string;
  name: string;
  uri: string;
  artists: string[];
  album_name: string | null;
  album_cover_url: string | null;
  duration_ms: number;
}

export interface SpotifyPlaybackState {
  is_playing: boolean;
  progress_ms: number;
  device_id: string | null;
  device_name: string | null;
  device_type: string | null;
  context_uri: string | null;
  context_type: string | null;
  volume_percent: number;
  current_track: SpotifyCurrentTrack | null;
}

export interface SpotifyPlayerResponse {
  playing: boolean;
  state: SpotifyPlaybackState | null;
}

// ─── API Client ────────────────────────────────────────────────────────────

export const spotifySystemApi = {
  getConfig: () => api.get<SpotifyAppConfigDto>('/system/spotify'),
  saveConfig: (data: SaveSpotifyAppConfigRequest) =>
    api.put<{ config_status: string; is_complete: boolean; updated_at: string }>('/system/spotify', data),
  validate: () =>
    api.post<{ valid: boolean; status: string; note: string }>('/system/spotify/validate', {}),
};

export const spotifyMusicApi = {
  getPlaylists: (profileId: string, offset = 0, limit = 50) =>
    api.get<SpotifyPlaylistsResponse>(`/profiles/${profileId}/spotify/playlists?offset=${offset}&limit=${limit}`),

  getPlaylistTracks: (profileId: string, playlistId: string, offset = 0, limit = 50) =>
    api.get<SpotifyPlaylistTracksResponse>(
      `/profiles/${profileId}/spotify/playlists/${playlistId}/tracks?offset=${offset}&limit=${limit}`,
    ),

  createPlaylist: (profileId: string, name: string, description?: string) =>
    api.post<SpotifyPlaylistItem>(`/profiles/${profileId}/spotify/playlists/create`, { name, description }),

  addTracks: (profileId: string, playlistId: string, uris: string[]) =>
    api.post<{ ok: boolean }>(`/profiles/${profileId}/spotify/playlists/${playlistId}/tracks`, { uris }),

  search: (profileId: string, q: string, type = 'playlist,track') =>
    api.get<SpotifySearchResponse>(`/profiles/${profileId}/spotify/search?q=${encodeURIComponent(q)}&type=${type}`),

  getPlayer: (profileId: string) =>
    api.get<SpotifyPlayerResponse>(`/profiles/${profileId}/spotify/player`),

  play: (profileId: string, contextUri: string, deviceId?: string) =>
    api.post<{ ok: boolean }>(`/profiles/${profileId}/spotify/playback/start`, {
      context_uri: contextUri,
      ...(deviceId ? { device_id: deviceId } : {}),
    }),

  pause: (profileId: string, deviceId?: string) =>
    api.post<{ ok: boolean }>(`/profiles/${profileId}/spotify/player/pause`, {
      ...(deviceId ? { device_id: deviceId } : {}),
    }),

  next: (profileId: string) =>
    api.post<{ ok: boolean }>(`/profiles/${profileId}/spotify/player/next`, {}),

  previous: (profileId: string) =>
    api.post<{ ok: boolean }>(`/profiles/${profileId}/spotify/player/previous`, {}),
};

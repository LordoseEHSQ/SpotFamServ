import { api } from '../client';

export interface SpotifyStatusResponse {
  status: string;
  link_id: string | null;
}

export interface SpotifyAuthUrlResponse {
  authorization_url: string;
  state: string;
  redirect_uri: string;
}

export interface SpotifyDeviceDto {
  id: string;
  name: string;
  type: string;
  is_active: boolean;
}

export interface SpotifyPlaylistDto {
  id: string;
  name: string;
  uri: string;
  owner_id: string | null;
}

export interface SpotifyPlaylistReferenceDto {
  id: string;
  name: string;
  spotify_playlist_id: string;
  owner_id: string | null;
}

export const spotifyApi = {
  getStatus: (profileId: string) =>
    api.get<SpotifyStatusResponse>(`/profiles/${profileId}/spotify/status`),
  listPlaylistReferences: (profileId: string) =>
    api.get<{ items: SpotifyPlaylistReferenceDto[] }>(`/profiles/${profileId}/spotify/playlist-references`),
  createPlaylistReference: (profileId: string, data: { spotify_playlist_id: string; name: string; owner_id?: string | null }) =>
    api.post<SpotifyPlaylistReferenceDto>(`/profiles/${profileId}/spotify/playlist-references`, data),
  getAuthorizationUrl: (profileId: string) =>
    api.get<SpotifyAuthUrlResponse>(`/profiles/${profileId}/spotify/authorization-url`),
  validate: (profileId: string) =>
    api.post<{ valid: boolean; spotify_user_id: string; display_name: string }>(`/profiles/${profileId}/spotify/validate`, {}),
  getDevices: (profileId: string) =>
    api.get<{ items: SpotifyDeviceDto[] }>(`/profiles/${profileId}/spotify/devices`),
  getPlaylists: (profileId: string, params?: { offset?: number; limit?: number }) => {
    const o = params?.offset ?? 0;
    const l = params?.limit ?? 50;
    return api.get<{ items: SpotifyPlaylistDto[] }>(`/profiles/${profileId}/spotify/playlists?offset=${o}&limit=${l}`);
  },
  startPlayback: (profileId: string, data: { context_uri: string; device_id?: string | null }) =>
    api.post<{ ok: boolean }>(`/profiles/${profileId}/spotify/playback/start`, data),
};

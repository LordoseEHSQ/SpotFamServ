import { api } from '../client';

export interface RfidCardDto {
  id: string;
  card_uid: string;
  label: string | null;
}

export interface RfidCardListResponse {
  items: RfidCardDto[];
}

export type CardPlaylistBindingDto = {
  id: string;
  name: string;
  spotify_playlist_id: string;
} | null;

export const rfidApi = {
  list: (profileId: string) =>
    api.get<RfidCardListResponse>(`/profiles/${profileId}/rfid-cards`),
  get: (profileId: string, cardId: string) =>
    api.get<RfidCardDto>(`/profiles/${profileId}/rfid-cards/${cardId}`),
  create: (profileId: string, data: { card_uid: string; label?: string | null }) =>
    api.post<RfidCardDto>(`/profiles/${profileId}/rfid-cards`, data),
  update: (profileId: string, cardId: string, data: { label?: string | null }) =>
    api.put<RfidCardDto>(`/profiles/${profileId}/rfid-cards/${cardId}`, data),
  delete: (profileId: string, cardId: string) =>
    api.delete(`/profiles/${profileId}/rfid-cards/${cardId}`),
  getBinding: (profileId: string, cardId: string) =>
    api.get<CardPlaylistBindingDto>(`/profiles/${profileId}/rfid-cards/${cardId}/binding`),
  setBinding: (profileId: string, cardId: string, spotify_playlist_reference_id: string | null) =>
    api.put<void>(`/profiles/${profileId}/rfid-cards/${cardId}/binding`, { spotify_playlist_reference_id }),
};

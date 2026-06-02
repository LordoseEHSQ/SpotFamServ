import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { rfidApi } from '../api/endpoints/rfid';
export type { RfidCardDto } from '../api/endpoints/rfid';
import { spotifyApi } from '../api/endpoints/spotify';

export const rfidCardKeys = {
  all: (profileId: string) => ['rfid-cards', profileId] as const,
  list: (profileId: string) => [...rfidCardKeys.all(profileId), 'list'] as const,
  binding: (profileId: string, cardId: string) => ['rfid-binding', profileId, cardId] as const,
  playlistRefs: (profileId: string) => ['playlist-references', profileId] as const,
};

export function useRfidCards(profileId: string | undefined) {
  return useQuery({
    queryKey: rfidCardKeys.list(profileId ?? ''),
    queryFn: () => rfidApi.list(profileId!),
    enabled: !!profileId,
  });
}

export function useCreatePlaylistReference(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: { spotify_playlist_id: string; name: string; owner_id?: string | null }) =>
      spotifyApi.createPlaylistReference(profileId, data),
    onSuccess: () => qc.invalidateQueries({ queryKey: rfidCardKeys.playlistRefs(profileId) }),
  });
}

export function useCardLookup(cardUid: string | null) {
  return useQuery({
    queryKey: ['card-lookup', cardUid],
    queryFn: () => rfidApi.lookup(cardUid!),
    enabled: !!cardUid,
  });
}

export function useCardBinding(profileId: string | undefined, cardId: string | undefined) {
  return useQuery({
    queryKey: rfidCardKeys.binding(profileId ?? '', cardId ?? ''),
    queryFn: () => rfidApi.getBinding(profileId!, cardId!),
    enabled: !!profileId && !!cardId,
  });
}

export function usePlaylistReferences(profileId: string | undefined, enabled = false) {
  return useQuery({
    queryKey: rfidCardKeys.playlistRefs(profileId ?? ''),
    queryFn: () => spotifyApi.listPlaylistReferences(profileId!),
    enabled: !!profileId && enabled,
  });
}

export function useCreateRfidCard(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: { card_uid: string; label?: string | null }) =>
      rfidApi.create(profileId, data),
    onSuccess: () => qc.invalidateQueries({ queryKey: rfidCardKeys.all(profileId) }),
  });
}

export function useUpdateRfidCard(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: { cardId: string; label: string | null }) =>
      rfidApi.update(profileId, data.cardId, { label: data.label }),
    onSuccess: () => qc.invalidateQueries({ queryKey: rfidCardKeys.all(profileId) }),
  });
}

export function useDeleteRfidCard(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (cardId: string) => rfidApi.delete(profileId, cardId),
    onSuccess: () => qc.invalidateQueries({ queryKey: rfidCardKeys.all(profileId) }),
  });
}

export function useSetCardBinding(profileId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: { cardId: string; refId: string | null }) =>
      rfidApi.setBinding(profileId, data.cardId, data.refId),
    onSuccess: (_, { cardId }) => {
      qc.invalidateQueries({ queryKey: rfidCardKeys.all(profileId) });
      qc.invalidateQueries({ queryKey: rfidCardKeys.binding(profileId, cardId) });
    },
  });
}

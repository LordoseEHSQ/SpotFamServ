<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Http;

use App\Module\Spotify\Application\CreatePlaylistReference;
use App\Module\Spotify\Application\ListPlaylistReferences;
use App\Module\Spotify\Application\GetAvailableDevices;
use App\Module\Spotify\Application\GetSpotifyAuthorizationUrl;
use App\Module\Spotify\Application\GetSpotifyStatus;
use App\Module\Spotify\Application\GetUserPlaylists;
use App\Module\Spotify\Application\SearchSpotify;
use App\Module\Spotify\Application\StartPlayback;
use App\Module\Spotify\Application\ValidateSpotifyConnection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/profiles/{profileId}/spotify', name: 'api_spotify_', format: 'json', requirements: ['profileId' => '%uuid_regex%'])]
final class SpotifyController
{
    public function __construct(
        private readonly GetSpotifyStatus $getStatus,
        private readonly GetSpotifyAuthorizationUrl $getAuthUrl,
        private readonly ValidateSpotifyConnection $validateConnection,
        private readonly GetUserPlaylists $getUserPlaylists,
        private readonly ListPlaylistReferences $listPlaylistReferences,
        private readonly CreatePlaylistReference $createPlaylistReference,
        private readonly SearchSpotify $searchSpotify,
        private readonly GetAvailableDevices $getAvailableDevices,
        private readonly StartPlayback $startPlayback,
    ) {
    }

    #[Route(path: '/authorization-url', name: 'authorization_url', methods: ['GET'])]
    public function authorizationUrl(string $profileId): JsonResponse
    {
        $result = ($this->getAuthUrl)($profileId);
        return new JsonResponse([
            'authorization_url' => $result->authorizationUrl,
            'state' => $result->state,
            'redirect_uri' => $result->redirectUri,
        ]);
    }

    #[Route(path: '/status', name: 'status', methods: ['GET'])]
    public function status(string $profileId): JsonResponse
    {
        $result = ($this->getStatus)($profileId);
        return new JsonResponse([
            'status' => $result->status,
            'link_id' => $result->linkId,
        ]);
    }

    #[Route(path: '/validate', name: 'validate', methods: ['POST'])]
    public function validate(string $profileId): JsonResponse
    {
        $result = ($this->validateConnection)($profileId);
        return new JsonResponse([
            'valid' => $result->valid,
            'spotify_user_id' => $result->spotifyUserId,
            'display_name' => $result->displayName,
        ]);
    }

    #[Route(path: '/playlist-references', name: 'playlist_references_list', methods: ['GET'])]
    public function playlistReferencesList(string $profileId): JsonResponse
    {
        $items = ($this->listPlaylistReferences)($profileId);
        return new JsonResponse([
            'items' => array_map(fn ($r) => [
                'id' => $r->getId(),
                'name' => $r->getName(),
                'spotify_playlist_id' => $r->getSpotifyPlaylistId(),
                'owner_id' => $r->getOwnerId(),
            ], $items),
        ]);
    }

    #[Route(path: '/playlist-references', name: 'playlist_references_create', methods: ['POST'])]
    public function playlistReferencesCreate(string $profileId, Request $request): JsonResponse
    {
        $body = $request->toArray();
        $spotifyPlaylistId = isset($body['spotify_playlist_id']) ? trim((string) $body['spotify_playlist_id']) : '';
        $name = isset($body['name']) ? trim((string) $body['name']) : '';
        $ownerId = isset($body['owner_id']) ? trim((string) $body['owner_id']) : null;
        if ($spotifyPlaylistId === '' || $name === '') {
            return new JsonResponse(['error' => 'spotify_playlist_id and name are required.'], 400);
        }
        $ref = ($this->createPlaylistReference)($profileId, $spotifyPlaylistId, $name, $ownerId);
        return new JsonResponse([
            'id' => $ref->getId(),
            'name' => $ref->getName(),
            'spotify_playlist_id' => $ref->getSpotifyPlaylistId(),
            'owner_id' => $ref->getOwnerId(),
        ], 201);
    }

    #[Route(path: '/playlists', name: 'playlists', methods: ['GET'])]
    public function playlists(string $profileId, Request $request): JsonResponse
    {
        $offset = max(0, (int) $request->query->get('offset', 0));
        $limit = min(50, max(1, (int) $request->query->get('limit', 50)));
        $items = ($this->getUserPlaylists)($profileId, $offset, $limit);
        return new JsonResponse([
            'items' => array_map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'uri' => $p->uri,
                'owner_id' => $p->ownerId,
            ], $items),
        ]);
    }

    #[Route(path: '/search', name: 'search', methods: ['GET'])]
    public function search(string $profileId, Request $request): JsonResponse
    {
        $query = $request->query->getString('q');
        if ($query === '') {
            return new JsonResponse(['playlists' => []], 400);
        }
        $types = $request->query->getString('type', 'playlist,track');
        $result = ($this->searchSpotify)($profileId, $query, $types);
        return new JsonResponse(['playlists' => $result->playlists]);
    }

    #[Route(path: '/devices', name: 'devices', methods: ['GET'])]
    public function devices(string $profileId): JsonResponse
    {
        $items = ($this->getAvailableDevices)($profileId);
        return new JsonResponse([
            'items' => array_map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'type' => $d->type,
                'is_active' => $d->isActive,
            ], $items),
        ]);
    }

    #[Route(path: '/playback/start', name: 'playback_start', methods: ['POST'])]
    public function playbackStart(string $profileId, Request $request): JsonResponse
    {
        $body = $request->toArray();
        $contextUri = $body['context_uri'] ?? '';
        $deviceId = isset($body['device_id']) ? (string) $body['device_id'] : null;
        if ($contextUri === '') {
            return new JsonResponse(['error' => 'context_uri required'], 400);
        }
        ($this->startPlayback)($profileId, $contextUri, $deviceId);
        return new JsonResponse(['ok' => true]);
    }
}

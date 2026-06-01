<?php

declare(strict_types=1);

namespace App\Module\FamilyProfile\Infrastructure\Http;

use App\Module\FamilyProfile\Application\CreateFamilyProfile;
use App\Module\FamilyProfile\Application\DeleteFamilyProfile;
use App\Module\FamilyProfile\Application\GetFamilyProfile;
use App\Module\FamilyProfile\Application\ListFamilyProfiles;
use App\Module\FamilyProfile\Application\SetDefaultDevice;
use App\Module\FamilyProfile\Application\UpdateFamilyProfile;
use App\Module\FamilyProfile\Domain\FamilyProfile;
use App\Module\FamilyProfile\Infrastructure\Http\Dto\FamilyProfileRequest;
use App\Module\Spotify\Application\Port\SpotifyAccountLinkRepositoryInterface;
use App\Module\Spotify\Domain\SpotifyAccountLink;
use App\Module\SetupWizard\Application\GetCompleteness;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/profiles', name: 'api_profiles_', format: 'json')]
final class FamilyProfileController
{
    public function __construct(
        private readonly ListFamilyProfiles $listProfiles,
        private readonly GetFamilyProfile $getProfile,
        private readonly CreateFamilyProfile $createProfile,
        private readonly UpdateFamilyProfile $updateProfile,
        private readonly DeleteFamilyProfile $deleteProfile,
        private readonly SetDefaultDevice $setDefaultDevice,
        private readonly SpotifyAccountLinkRepositoryInterface $accountLinkRepository,
        private readonly GetCompleteness $getCompleteness,
    ) {}

    #[Route(name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $profiles = ($this->listProfiles)();
        return new JsonResponse([
            'items' => array_map([$this, 'profileToArray'], $profiles),
        ]);
    }

    #[Route(path: '/{id}', name: 'get', requirements: ['id' => '%uuid_regex%'], methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $profile = ($this->getProfile)($id);
        return new JsonResponse($this->profileToArray($profile));
    }

    #[Route(name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body    = FamilyProfileRequest::fromRequest($request);
        $profile = ($this->createProfile)($body->name, $body->description);
        return new JsonResponse($this->profileToArray($profile), Response::HTTP_CREATED);
    }

    #[Route(path: '/{id}', name: 'update', requirements: ['id' => '%uuid_regex%'], methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $body    = FamilyProfileRequest::fromRequest($request);
        $profile = ($this->updateProfile)($id, $body->name, $body->description);
        return new JsonResponse($this->profileToArray($profile));
    }

    #[Route(path: '/{id}', name: 'delete', requirements: ['id' => '%uuid_regex%'], methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        ($this->deleteProfile)($id);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/{id}/default-device', name: 'set_default_device', requirements: ['id' => '%uuid_regex%'], methods: ['PUT'])]
    public function setDefaultDevice(string $id, Request $request): JsonResponse
    {
        $body     = $request->toArray();
        $deviceId = isset($body['device_id']) ? trim((string) $body['device_id']) : '';
        if ($deviceId === '') {
            return new JsonResponse(['error' => 'device_id is required.'], Response::HTTP_BAD_REQUEST);
        }
        $deviceName = isset($body['device_name']) ? (string) $body['device_name'] : null;
        $profile    = ($this->setDefaultDevice)($id, $deviceId, $deviceName);
        return new JsonResponse($this->profileToArray($profile));
    }

    #[Route(path: '/{id}/default-device', name: 'clear_default_device', requirements: ['id' => '%uuid_regex%'], methods: ['DELETE'])]
    public function clearDefaultDevice(string $id): JsonResponse
    {
        $profile = ($this->setDefaultDevice)($id, null, null);
        return new JsonResponse($this->profileToArray($profile));
    }

    private function profileToArray(FamilyProfile $p): array
    {
        $link          = $this->accountLinkRepository->findByProfileId((string) $p->getId());
        $spotifyStatus = $this->resolveSpotifyStatus($link);

        $completeness = null;
        try {
            $completeness = ($this->getCompleteness)((string) $p->getId());
        } catch (\Throwable) {
            // kein Setup gestartet – kein Fehler werfen
        }

        return [
            'id'                        => $p->getId(),
            'name'                      => $p->getName(),
            'description'               => $p->getDescription(),
            'status'                    => $p->getStatus(),
            'default_spotify_device_id' => $p->getDefaultSpotifyDeviceId(),
            'default_device_name'       => $p->getDefaultDeviceName(),
            'spotify_status'            => $spotifyStatus,
            'spotify_user_display_name' => $link?->getSpotifyDisplayName() ?? $link?->getSpotifyUserId(),
            'setup_complete'            => $completeness !== null && $completeness->percent >= 100,
            'setup_percent'             => $completeness?->percent ?? 0,
            'last_activity_at'          => null, // wird in zukünftiger Version aus ActivityLog geladen
            'created_at'                => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at'                => $p->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function resolveSpotifyStatus(?SpotifyAccountLink $link): string
    {
        if ($link === null) {
            return 'not_connected';
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // Wenn Token bereits abgelaufen (mit 5-Minuten-Puffer)
        if ($link->getExpiresAt() < $now->modify('-5 minutes')) {
            return 'expired';
        }

        return 'connected';
    }
}

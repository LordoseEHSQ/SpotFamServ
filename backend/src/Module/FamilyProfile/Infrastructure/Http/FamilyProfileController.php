<?php

declare(strict_types=1);

namespace App\Module\FamilyProfile\Infrastructure\Http;

use App\Module\FamilyProfile\Application\CreateFamilyProfile;
use App\Module\FamilyProfile\Application\DeleteFamilyProfile;
use App\Module\FamilyProfile\Application\GetFamilyProfile;
use App\Module\FamilyProfile\Application\ListFamilyProfiles;
use App\Module\FamilyProfile\Application\UpdateFamilyProfile;
use App\Module\FamilyProfile\Domain\FamilyProfile;
use App\Module\FamilyProfile\Infrastructure\Http\Dto\FamilyProfileRequest;
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
    ) {
    }

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
        $body = FamilyProfileRequest::fromRequest($request);
        $profile = ($this->createProfile)($body->name, $body->description);
        return new JsonResponse($this->profileToArray($profile), Response::HTTP_CREATED);
    }

    #[Route(path: '/{id}', name: 'update', requirements: ['id' => '%uuid_regex%'], methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $body = FamilyProfileRequest::fromRequest($request);
        $profile = ($this->updateProfile)($id, $body->name, $body->description);
        return new JsonResponse($this->profileToArray($profile));
    }

    #[Route(path: '/{id}', name: 'delete', requirements: ['id' => '%uuid_regex%'], methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        ($this->deleteProfile)($id);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function profileToArray(FamilyProfile $p): array
    {
        return [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'description' => $p->getDescription(),
            'default_spotify_device_id' => $p->getDefaultSpotifyDeviceId(),
            'created_at' => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $p->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}

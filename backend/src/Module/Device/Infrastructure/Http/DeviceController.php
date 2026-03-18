<?php

declare(strict_types=1);

namespace App\Module\Device\Infrastructure\Http;

use App\Module\Device\Application\AssignDevice;
use App\Module\Device\Application\RunDeviceDiscovery;
use App\Module\Device\Application\Port\SpotifyDeviceRepositoryInterface;
use App\Module\Device\Application\Port\DeviceDiscoveryRunRepositoryInterface;
use App\Module\Device\Domain\SpotifyDevice;
use App\Module\Device\Domain\DeviceDiscoveryRun;
use App\Module\FamilyProfile\Application\Port\FamilyProfileRepositoryInterface;
use App\Shared\Application\Exception\NotFoundException;
use App\Shared\Infrastructure\Http\ProblemJsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('')]
final class DeviceController extends AbstractController
{
    public function __construct(
        private readonly SpotifyDeviceRepositoryInterface $deviceRepository,
        private readonly DeviceDiscoveryRunRepositoryInterface $runRepository,
        private readonly FamilyProfileRepositoryInterface $profileRepository,
        private readonly RunDeviceDiscovery $runDiscovery,
        private readonly AssignDevice $assignDevice,
    ) {}

    #[Route('/devices', name: 'api_devices_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $devices = $this->deviceRepository->findAll();

        return $this->json([
            'items' => array_map(fn (SpotifyDevice $d) => $this->serialize($d), $devices),
            'total' => count($devices),
        ]);
    }

    #[Route('/devices/{id}', name: 'api_devices_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        try {
            $uuid   = Uuid::fromString($id);
            $device = $this->deviceRepository->findById($uuid);
        } catch (\Throwable) {
            return new ProblemJsonResponse('Ungültige Geräte-ID.', Response::HTTP_BAD_REQUEST);
        }

        if ($device === null) {
            return new ProblemJsonResponse('Gerät nicht gefunden.', Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($device));
    }

    #[Route('/devices/{id}/assign', name: 'api_devices_assign', methods: ['PUT'])]
    public function assign(string $id, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];

        try {
            $device = ($this->assignDevice)(
                deviceId:  $id,
                profileId: $body['family_profile_id'] ?? null,
                mode:      $body['assignment_mode'] ?? SpotifyDevice::ASSIGNMENT_ASSIGNED,
                note:      $body['assignment_note'] ?? null,
                force:     (bool) ($body['force'] ?? false),
            );
        } catch (NotFoundException $e) {
            return new ProblemJsonResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ConflictHttpException $e) {
            return new ProblemJsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->json($this->serialize($device));
    }

    #[Route('/devices/discover', name: 'api_devices_discover', methods: ['POST'])]
    public function discover(Request $request): JsonResponse
    {
        $body      = json_decode($request->getContent(), true) ?? [];
        $profileId = $body['profile_id'] ?? null;

        $run = ($this->runDiscovery)($profileId);

        return $this->json($this->serializeRun($run), Response::HTTP_CREATED);
    }

    #[Route('/devices/discovery-runs/latest', name: 'api_devices_discovery_latest', methods: ['GET'])]
    public function latestRun(): JsonResponse
    {
        $run = $this->runRepository->findLatest();
        if ($run === null) {
            return $this->json(null);
        }

        return $this->json($this->serializeRun($run));
    }

    #[Route('/devices/discovery-runs', name: 'api_devices_discovery_runs', methods: ['GET'])]
    public function runs(Request $request): JsonResponse
    {
        $limit = min((int) ($request->query->get('limit', 10)), 50);
        $runs  = $this->runRepository->findRecent($limit);

        return $this->json([
            'items' => array_map(fn (DeviceDiscoveryRun $r) => $this->serializeRun($r), $runs),
        ]);
    }

    private function serialize(SpotifyDevice $d): array
    {
        $profileName = null;
        if ($d->getAssignedFamilyProfileId() !== null) {
            $profile     = $this->profileRepository->find((string) $d->getAssignedFamilyProfileId());
            $profileName = $profile?->getName();
        }

        return [
            'id'                         => (string) $d->getId(),
            'spotify_device_id'          => $d->getSpotifyDeviceId(),
            'spotify_device_name'        => $d->getSpotifyDeviceName(),
            'device_type'                => $d->getDeviceType(),
            'is_available'               => $d->isAvailable(),
            'last_seen_at'               => $d->getLastSeenAt()?->format(\DateTimeInterface::ATOM),
            'assigned_family_profile_id' => $d->getAssignedFamilyProfileId() !== null ? (string) $d->getAssignedFamilyProfileId() : null,
            'assigned_profile_name'      => $profileName,
            'assignment_mode'            => $d->getAssignmentMode(),
            'assignment_updated_at'      => $d->getAssignmentUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'assignment_note'            => $d->getAssignmentNote(),
            'discovery_status'           => $d->getDiscoveryStatus(),
            'created_at'                 => $d->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at'                 => $d->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function serializeRun(DeviceDiscoveryRun $r): array
    {
        return [
            'id'                      => (string) $r->getId(),
            'started_at'              => $r->getStartedAt()->format(\DateTimeInterface::ATOM),
            'finished_at'             => $r->getFinishedAt()?->format(\DateTimeInterface::ATOM),
            'scope'                   => $r->getScope(),
            'scope_profile_id'        => $r->getScopeProfileId() !== null ? (string) $r->getScopeProfileId() : null,
            'result_status'           => $r->getResultStatus(),
            'devices_found_count'     => $r->getDevicesFoundCount(),
            'devices_available_count' => $r->getDevicesAvailableCount(),
            'devices_new_count'       => $r->getDevicesNewCount(),
            'error_message'           => $r->getErrorMessage(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Infrastructure\Http;

use App\Module\Provisioning\Application\CreateJob;
use App\Module\Provisioning\Application\GetJob;
use App\Module\Provisioning\Application\ListArtifacts;
use App\Module\Provisioning\Application\ListDevices;
use App\Module\Provisioning\Application\ProvisioningException;
use App\Module\Provisioning\Domain\DetectedDevice;
use App\Module\Provisioning\Domain\FlashArtifact;
use App\Module\Provisioning\Domain\FlashJob;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin/Web-Endpunkte für die Provisioning-Verwaltung.
 * Bewusst ohne Agent-Key-Auth – so offen wie der restliche Admin-Bereich.
 * Sollte in Produktion hinter denselben Auth-Mechanismus wie das restliche Admin-Frontend gestellt werden.
 */
#[Route(path: '/provisioning', name: 'api_provisioning_admin_', format: 'json')]
final class ProvisioningAdminController
{
    public function __construct(
        private readonly ListDevices $listDevices,
        private readonly ListArtifacts $listArtifacts,
        private readonly CreateJob $createJob,
        private readonly GetJob $getJob,
    ) {
    }

    /**
     * GET /api/v1/provisioning/devices
     * Liste aller erkannten Geräte inkl. letztem Job.
     */
    #[Route(path: '/devices', name: 'list_devices', methods: ['GET'])]
    public function listDevices(): JsonResponse
    {
        $rows = ($this->listDevices)();

        $items = array_map(function (array $row): array {
            /** @var DetectedDevice $device */
            $device = $row['device'];
            /** @var FlashJob|null $latestJob */
            $latestJob = $row['latestJob'];

            return [
                'id'              => $device->getId(),
                'port'            => $device->getPort(),
                'chip'            => $device->getChip(),
                'chipDescription' => $device->getChipDescription(),
                'mac'             => $device->getMac(),
                'flashSize'       => $device->getFlashSize(),
                'status'          => $device->getStatus(),
                'firstSeenAt'     => $device->getFirstSeenAt()->format(\DateTimeInterface::ATOM),
                'lastSeenAt'      => $device->getLastSeenAt()->format(\DateTimeInterface::ATOM),
                'latestJob'       => $latestJob !== null ? [
                    'jobId'    => $latestJob->getId(),
                    'status'   => $latestJob->getStatus(),
                    'progress' => $latestJob->getProgress(),
                ] : null,
            ];
        }, $rows);

        return new JsonResponse(['items' => $items]);
    }

    /**
     * GET /api/v1/provisioning/artifacts
     * Liste aller registrierten Firmware-Artefakte.
     */
    #[Route(path: '/artifacts', name: 'list_artifacts', methods: ['GET'])]
    public function listArtifacts(): JsonResponse
    {
        $artifacts = ($this->listArtifacts)();

        $items = array_map(fn (FlashArtifact $a): array => [
            'id'           => $a->getId(),
            'board'        => $a->getBoard(),
            'channel'      => $a->getChannel(),
            'version'      => $a->getVersion(),
            'filename'     => $a->getFilename(),
            'sha256'       => $a->getSha256(),
            'expectedChip' => $a->getExpectedChip(),
            'sizeBytes'    => $a->getSizeBytes(),
            'createdAt'    => $a->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $artifacts);

        return new JsonResponse(['items' => $items]);
    }

    /**
     * POST /api/v1/provisioning/jobs
     * Legt einen neuen Flash-Job an. 404 wenn Gerät/Artefakt fehlt, 409 bei aktivem Job.
     */
    #[Route(path: '/jobs', name: 'create_job', methods: ['POST'])]
    public function createJob(Request $request): JsonResponse
    {
        $body       = $this->parseBody($request);
        $deviceId   = trim((string) ($body['deviceId'] ?? ''));
        $artifactId = trim((string) ($body['artifactId'] ?? ''));

        if ($deviceId === '' || $artifactId === '') {
            return new JsonResponse(['error' => 'deviceId und artifactId sind Pflichtfelder.'], 400);
        }

        try {
            $job = ($this->createJob)($deviceId, $artifactId);
        } catch (ProvisioningException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->errorCode], $e->statusCode);
        }

        return new JsonResponse([
            'jobId'      => $job->getId(),
            'deviceId'   => $job->getDevice()->getId(),
            'artifactId' => $job->getArtifact()->getId(),
            'status'     => $job->getStatus(),
            'progress'   => $job->getProgress(),
            'createdAt'  => $job->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/v1/provisioning/jobs/{jobId}
     * Gibt den aktuellen Status eines Jobs zurück.
     */
    #[Route(path: '/jobs/{jobId}', name: 'get_job', methods: ['GET'])]
    public function getJob(string $jobId): JsonResponse
    {
        try {
            $job = ($this->getJob)($jobId);
        } catch (ProvisioningException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->errorCode], $e->statusCode);
        }

        return new JsonResponse([
            'jobId'      => $job->getId(),
            'deviceId'   => $job->getDevice()->getId(),
            'artifactId' => $job->getArtifact()->getId(),
            'status'     => $job->getStatus(),
            'progress'   => $job->getProgress(),
            'message'    => $job->getMessage(),
            'updatedAt'  => $job->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    /** @return array<string, mixed> */
    private function parseBody(Request $request): array
    {
        if ($request->getContent() === '') {
            return [];
        }
        /** @var array<string, mixed> $data */
        $data = $request->toArray();
        return $data;
    }
}

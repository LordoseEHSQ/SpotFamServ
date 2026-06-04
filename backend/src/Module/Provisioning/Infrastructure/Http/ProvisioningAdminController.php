<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Infrastructure\Http;

use App\Module\Provisioning\Application\CreateJob;
use App\Module\Provisioning\Application\GetJob;
use App\Module\Provisioning\Application\ListArtifacts;
use App\Module\Provisioning\Application\ListDevices;
use App\Module\Provisioning\Application\ProvisioningException;
use App\Module\Provisioning\Application\RegisterArtifact;
use App\Module\Provisioning\Domain\DetectedDevice;
use App\Module\Provisioning\Domain\FlashArtifact;
use App\Module\Provisioning\Domain\FlashJob;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Admin/Web-Endpunkte für die Provisioning-Verwaltung (ROLE_ADMIN via Catch-all).
 */
#[Route(path: '/provisioning', name: 'api_provisioning_admin_', format: 'json')]
final class ProvisioningAdminController
{
    /** Max. Dateigröße für Firmware-Uploads in Bytes (8 MB). */
    private const FIRMWARE_UPLOAD_MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(
        private readonly ListDevices $listDevices,
        private readonly ListArtifacts $listArtifacts,
        private readonly CreateJob $createJob,
        private readonly GetJob $getJob,
        private readonly RegisterArtifact $registerArtifact,
        private readonly string $firmwareDir,
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
    #[Route(path: '/jobs/{jobId}', name: 'get_job', methods: ['GET'], requirements: ['jobId' => Requirement::UUID])]
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

    /**
     * POST /api/v1/provisioning/artifacts
     *
     * Authentifizierter Firmware-Upload (multipart/form-data).
     * Felder: file (binary), board, channel, version, expectedChip.
     * Geschützt durch ROLE_ADMIN-Catch-all (D-027).
     */
    #[Route(path: '/artifacts', name: 'upload_artifact', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/provisioning/artifacts',
        summary: 'Firmware-Artefakt hochladen und registrieren',
        tags: ['Provisioning'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(properties: [
                    new OA\Property(property: 'file', type: 'string', format: 'binary'),
                    new OA\Property(property: 'board', type: 'string', example: 'esp32-wroom-32'),
                    new OA\Property(property: 'channel', type: 'string', example: 'stable'),
                    new OA\Property(property: 'version', type: 'string', example: '1.2.3'),
                    new OA\Property(property: 'expectedChip', type: 'string', example: 'ESP32-D0WD-V3'),
                ]),
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Artefakt registriert'),
            new OA\Response(response: 400, description: 'Validierungsfehler'),
            new OA\Response(response: 401, description: 'Nicht eingeloggt'),
        ],
    )]
    public function uploadArtifact(Request $request): JsonResponse
    {
        $file         = $request->files->get('file');
        $board        = trim((string) $request->request->get('board', ''));
        $channel      = trim((string) $request->request->get('channel', ''));
        $version      = trim((string) $request->request->get('version', ''));
        $expectedChip = trim((string) $request->request->get('expectedChip', ''));

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'Pflichtfeld `file` fehlt oder ist keine Datei.'], Response::HTTP_BAD_REQUEST);
        }
        if ($board === '' || $channel === '' || $version === '' || $expectedChip === '') {
            return new JsonResponse(['error' => 'board, channel, version und expectedChip sind Pflichtfelder.'], Response::HTTP_BAD_REQUEST);
        }
        if ($file->getSize() > self::FIRMWARE_UPLOAD_MAX_BYTES) {
            return new JsonResponse(['error' => 'Datei überschreitet das Größenlimit von 8 MB.'], Response::HTTP_BAD_REQUEST);
        }

        $originalName = $file->getClientOriginalName();
        $filename     = basename($originalName);
        if (
            $filename === ''
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || str_contains($filename, '..')
            || str_contains($filename, "\0")
        ) {
            return new JsonResponse(['error' => 'Ungültiger Dateiname.'], Response::HTTP_BAD_REQUEST);
        }

        $targetDir  = rtrim($this->firmwareDir, '/');
        $targetPath = $targetDir . '/' . $filename;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            return new JsonResponse(['error' => 'FIRMWARE_DIR konnte nicht erstellt werden.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $file->move($targetDir, $filename);
        } catch (\Exception) {
            return new JsonResponse(['error' => 'Datei konnte nicht gespeichert werden.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $artifact = ($this->registerArtifact)($board, $channel, $version, $filename, $expectedChip, $targetPath);
        } catch (ProvisioningException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->errorCode], $e->statusCode);
        }

        return new JsonResponse([
            'id'           => $artifact->getId(),
            'board'        => $artifact->getBoard(),
            'channel'      => $artifact->getChannel(),
            'version'      => $artifact->getVersion(),
            'expectedChip' => $artifact->getExpectedChip(),
            'sha256'       => $artifact->getSha256(),
            'sizeBytes'    => $artifact->getSizeBytes(),
            'filename'     => $artifact->getFilename(),
        ], Response::HTTP_CREATED);
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

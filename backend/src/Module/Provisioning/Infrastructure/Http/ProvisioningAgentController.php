<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Infrastructure\Http;

use App\Module\Provisioning\Application\DetectDevice;
use App\Module\Provisioning\Application\GetNextJob;
use App\Module\Provisioning\Application\ProvisioningException;
use App\Module\Provisioning\Application\UpdateJobStatus;
use App\Module\Provisioning\Domain\FlashJob;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Agent-Endpunkte für die Flash-Station (D-024).
 * Alle Methoden verlangen den Header X-API-Key == FLASH_AGENT_API_KEY.
 * Ist FLASH_AGENT_API_KEY leer (Dev-Default), bleibt auth offen – analog READER_API_KEY-Muster.
 */
#[Route(path: '/provisioning', name: 'api_provisioning_agent_', format: 'json')]
final class ProvisioningAgentController
{
    public function __construct(
        private readonly DetectDevice $detectDevice,
        private readonly GetNextJob $getNextJob,
        private readonly UpdateJobStatus $updateJobStatus,
        private readonly string $flashAgentApiKey = '',
    ) {
    }

    /**
     * POST /api/v1/provisioning/devices/detect
     * Upsert eines erkannten ESP32-Geräts; ActivityLog nur bei Neuanlage.
     */
    #[Route(path: '/devices/detect', name: 'detect_device', methods: ['POST'])]
    public function detectDevice(Request $request): JsonResponse
    {
        if (!$this->validateAgentAuth($request)) {
            return $this->unauthorized();
        }

        $body = $this->parseBody($request);

        $port            = trim((string) ($body['port'] ?? ''));
        $chip            = trim((string) ($body['chip'] ?? ''));
        $chipDescription = trim((string) ($body['chipDescription'] ?? ''));
        $mac             = trim((string) ($body['mac'] ?? ''));
        $flashSize       = trim((string) ($body['flashSize'] ?? ''));

        if ($port === '' || $chip === '' || $mac === '' || $flashSize === '') {
            return new JsonResponse(['error' => 'port, chip, mac und flashSize sind Pflichtfelder.'], 400);
        }

        try {
            $result = ($this->detectDevice)($port, $chip, $chipDescription, $mac, $flashSize);
        } catch (ProvisioningException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->errorCode], $e->statusCode);
        }

        return new JsonResponse(['deviceId' => $result->deviceId, 'status' => $result->status]);
    }

    /**
     * GET /api/v1/provisioning/jobs/next?deviceId={id}
     * Liefert den ältesten pending-Job oder 204 wenn keiner vorhanden.
     */
    // priority > Standard, damit '/jobs/next' VOR der Admin-Route '/jobs/{jobId}'
    // matcht (sonst wuerde 'next' als jobId interpretiert -> 500 UUID-Cast).
    #[Route(path: '/jobs/next', name: 'get_next_job', methods: ['GET'], priority: 10)]
    public function getNextJob(Request $request): JsonResponse
    {
        if (!$this->validateAgentAuth($request)) {
            return $this->unauthorized();
        }

        $deviceId = trim($request->query->getString('deviceId'));
        if ($deviceId === '') {
            return new JsonResponse(['error' => 'deviceId ist ein Pflichtparameter.'], 400);
        }

        try {
            $job = ($this->getNextJob)($deviceId);
        } catch (ProvisioningException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->errorCode], $e->statusCode);
        }

        if ($job === null) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $artifact = $job->getArtifact();

        return new JsonResponse([
            'jobId'    => $job->getId(),
            'deviceId' => $job->getDevice()->getId(),
            'artifact' => [
                'id'           => $artifact->getId(),
                'filename'     => $artifact->getFilename(),
                'sha256'       => $artifact->getSha256(),
                'expectedChip' => $artifact->getExpectedChip(),
                'board'        => $artifact->getBoard(),
                'version'      => $artifact->getVersion(),
            ],
        ]);
    }

    /**
     * POST /api/v1/provisioning/jobs/{jobId}/status
     * Meldet Statusänderung eines Jobs (running|success|failed).
     */
    #[Route(path: '/jobs/{jobId}/status', name: 'update_job_status', methods: ['POST'])]
    public function updateJobStatus(string $jobId, Request $request): JsonResponse
    {
        if (!$this->validateAgentAuth($request)) {
            return $this->unauthorized();
        }

        $body    = $this->parseBody($request);
        $status  = trim((string) ($body['status'] ?? ''));
        $progress = isset($body['progress']) ? (int) $body['progress'] : null;
        $message  = isset($body['message']) ? (string) $body['message'] : null;

        if ($status === '') {
            return new JsonResponse(['error' => 'status ist ein Pflichtfeld.'], 400);
        }

        try {
            ($this->updateJobStatus)($jobId, $status, $progress, $message);
        } catch (ProvisioningException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->errorCode], $e->statusCode);
        }

        return new JsonResponse(['jobId' => $jobId, 'status' => $status]);
    }

    /**
     * Auth-Guard für Agent-Endpunkte (D-024).
     * Ist FLASH_AGENT_API_KEY leer (Dev-Default) → offen, analog READER_API_KEY-Muster.
     * Im Produktionsbetrieb MUSS FLASH_AGENT_API_KEY gesetzt werden.
     */
    private function validateAgentAuth(Request $request): bool
    {
        if ($this->flashAgentApiKey === '') {
            return true;
        }

        $presented = $request->headers->get('X-API-Key', '');

        return $presented !== '' && hash_equals($this->flashAgentApiKey, $presented);
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Fehlender oder ungültiger X-API-Key-Header.'],
            Response::HTTP_UNAUTHORIZED,
        );
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

<?php

declare(strict_types=1);

namespace App\Module\Health\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Öffentlicher Liveness-/Readiness-Endpunkt für das Deploy-/Monitoring.
 * Public (kein Auth), damit der Pi-Auto-Deploy-Healthcheck nach Aktivierung der
 * Admin-Auth nicht mehr am 401 der geschützten API scheitert.
 */
#[Route(path: '', format: 'json')]
final class HealthController
{
    public function __construct(private readonly Connection $db)
    {
    }

    #[Route(path: '/health', name: 'api_health', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/health',
        summary: 'Liveness/Readiness (DB-Ping); public.',
        responses: [
            new OA\Response(response: 200, description: 'OK – App + DB erreichbar'),
            new OA\Response(response: 503, description: 'DB nicht erreichbar'),
        ],
    )]
    public function health(): JsonResponse
    {
        try {
            $this->db->executeQuery('SELECT 1');
        } catch (\Throwable) {
            return new JsonResponse(['status' => 'degraded', 'db' => 'down'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}

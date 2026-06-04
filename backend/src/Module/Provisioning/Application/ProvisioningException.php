<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application;

use Symfony\Component\HttpFoundation\Response;

/**
 * Anwendungsschicht-Exception für das Provisioning-Modul.
 * Trägt HTTP-Statuscode und einen maschinenlesbaren Fehlercode.
 */
final class ProvisioningException extends \RuntimeException
{
    private function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode,
    ) {
        parent::__construct($message);
    }

    public static function deviceNotFound(string $deviceId): self
    {
        return new self('device_not_found', sprintf('Gerät %s nicht gefunden.', $deviceId), Response::HTTP_NOT_FOUND);
    }

    public static function artifactNotFound(string $artifactId): self
    {
        return new self('artifact_not_found', sprintf('Artefakt %s nicht gefunden.', $artifactId), Response::HTTP_NOT_FOUND);
    }

    public static function jobNotFound(string $jobId): self
    {
        return new self('job_not_found', sprintf('Job %s nicht gefunden.', $jobId), Response::HTTP_NOT_FOUND);
    }

    public static function activeJobExists(string $deviceId): self
    {
        return new self(
            'active_job_exists',
            sprintf('Für Gerät %s existiert bereits ein aktiver Job (pending|running).', $deviceId),
            Response::HTTP_CONFLICT,
        );
    }

    public static function invalidStatusTransition(string $from, string $to): self
    {
        return new self(
            'invalid_status_transition',
            sprintf('Statusübergang %s→%s ist nicht erlaubt.', $from, $to),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    public static function invalidRequest(string $detail): self
    {
        return new self('invalid_request', $detail, Response::HTTP_BAD_REQUEST);
    }
}

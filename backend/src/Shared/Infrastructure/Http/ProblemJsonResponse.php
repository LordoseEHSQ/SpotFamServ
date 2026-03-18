<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Application\Exception\HttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds RFC 7807 Problem+JSON responses.
 */
final class ProblemJsonResponse
{
    public static function fromThrowable(\Throwable $e, string $instance = ''): JsonResponse
    {
        if ($e instanceof HttpException) {
            return self::build($e->getStatusCode(), $e->getMessage(), $instance);
        }
        return self::build(Response::HTTP_INTERNAL_SERVER_ERROR, 'An unexpected error occurred.', $instance);
    }

    public static function fromDomainException(\Throwable $e, int $statusCode, string $instance = ''): JsonResponse
    {
        return self::build($statusCode, $e->getMessage(), $instance);
    }

    private static function build(int $status, string $detail, string $instance): JsonResponse
    {
        $body = [
            'type' => 'https://example.com/errors/' . self::typeFromStatus($status),
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
            'detail' => $detail,
        ];
        if ($instance !== '') {
            $body['instance'] = $instance;
        }
        return new JsonResponse($body, $status, ['Content-Type' => 'application/problem+json']);
    }

    private static function typeFromStatus(int $status): string
    {
        return match (true) {
            $status >= 500 => 'server-error',
            $status === 404 => 'not-found',
            $status === 422 => 'validation-error',
            $status === 401 => 'unauthorized',
            $status >= 400 => 'client-error',
            default => 'error',
        };
    }
}

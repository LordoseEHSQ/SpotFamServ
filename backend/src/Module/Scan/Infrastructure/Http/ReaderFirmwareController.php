<?php

declare(strict_types=1);

namespace App\Module\Scan\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/readers/firmware', name: 'api_reader_firmware_', format: 'json')]
final class ReaderFirmwareController
{
    private const SUPPORTED_BOARD = 'esp32-wroom-32';
    private const SUPPORTED_CHANNEL = 'stable';

    #[Route(path: '/manifest', name: 'manifest', methods: ['GET'])]
    public function manifest(Request $request): Response
    {
        $board = strtolower(trim($request->query->getString('board')));
        $channel = strtolower(trim($request->query->getString('channel', self::SUPPORTED_CHANNEL)));
        $currentVersion = trim($request->query->getString('current_version'));

        if ($board === '' || $currentVersion === '') {
            return $this->error('invalid_request', 'board and current_version are required.', Response::HTTP_BAD_REQUEST);
        }

        if ($board !== self::SUPPORTED_BOARD || $channel !== self::SUPPORTED_CHANNEL) {
            return $this->error('unsupported_board', 'Board or firmware channel is not supported.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (preg_match('/^\d+\.\d+\.\d+$/', $currentVersion) !== 1) {
            return $this->error('invalid_request', 'current_version must use MAJOR.MINOR.PATCH.', Response::HTTP_BAD_REQUEST);
        }

        // No firmware artifact is published by this software slice yet. Returning 204
        // gives the ESP a stable "no update" contract without pretending OTA is done.
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message], $status);
    }
}

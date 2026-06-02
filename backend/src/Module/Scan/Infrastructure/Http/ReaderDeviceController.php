<?php

declare(strict_types=1);

namespace App\Module\Scan\Infrastructure\Http;

use App\Module\Scan\Application\GenerateReaderApiKey;
use App\Module\Scan\Application\ListReaderDevices;
use App\Module\Scan\Application\RevokeReaderApiKey;
use App\Module\Scan\Application\SetReaderDefaultDevice;
use App\Module\Scan\Domain\ReaderDevice;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin endpoints for readers, their Reader→Box mapping (D-015) and per-reader
 * API keys (D-K1). Readers register themselves on first scan; here they are listed,
 * mapped to a room box and (optionally) given a dedicated API key.
 */
#[Route(path: '/readers', name: 'api_readers_', format: 'json')]
final class ReaderDeviceController
{
    public function __construct(
        private readonly ListReaderDevices $listReaders,
        private readonly SetReaderDefaultDevice $setDefaultDevice,
        private readonly GenerateReaderApiKey $generateApiKey,
        private readonly RevokeReaderApiKey $revokeApiKey,
    ) {
    }

    #[Route(name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $readers = ($this->listReaders)();
        return new JsonResponse([
            'items' => array_map([$this, 'readerToArray'], $readers),
        ]);
    }

    #[Route(path: '/{readerId}/default-device', name: 'set_default_device', methods: ['PUT'], requirements: ['readerId' => '[^/]+'])]
    public function setDefaultDeviceRoute(string $readerId, Request $request): JsonResponse
    {
        $body = $request->toArray();
        $deviceId = isset($body['device_id']) ? trim((string) $body['device_id']) : '';
        if ($deviceId === '') {
            return new JsonResponse(['error' => 'device_id is required.'], Response::HTTP_BAD_REQUEST);
        }
        $deviceName = isset($body['device_name']) ? (string) $body['device_name'] : null;
        $reader = ($this->setDefaultDevice)($readerId, $deviceId, $deviceName);
        return new JsonResponse($this->readerToArray($reader));
    }

    #[Route(path: '/{readerId}/default-device', name: 'clear_default_device', methods: ['DELETE'], requirements: ['readerId' => '[^/]+'])]
    public function clearDefaultDevice(string $readerId): JsonResponse
    {
        $reader = ($this->setDefaultDevice)($readerId, null, null);
        return new JsonResponse($this->readerToArray($reader));
    }

    /**
     * Generates (or rotates) a dedicated API key for this reader. The plain key is
     * returned exactly once and never stored or logged; only its hash is persisted.
     */
    #[Route(path: '/{readerId}/api-key', name: 'generate_api_key', methods: ['POST'], requirements: ['readerId' => '[^/]+'])]
    public function generateApiKeyRoute(string $readerId): JsonResponse
    {
        $plainKey = ($this->generateApiKey)($readerId);
        return new JsonResponse([
            'reader_id' => $readerId,
            'api_key' => $plainKey,
        ], Response::HTTP_CREATED);
    }

    /**
     * Removes the reader's dedicated API key, re-activating the global-key fallback.
     */
    #[Route(path: '/{readerId}/api-key', name: 'revoke_api_key', methods: ['DELETE'], requirements: ['readerId' => '[^/]+'])]
    public function revokeApiKeyRoute(string $readerId): JsonResponse
    {
        $reader = ($this->revokeApiKey)($readerId);
        return new JsonResponse($this->readerToArray($reader));
    }

    /**
     * @return array{
     *     id: string|null,
     *     reader_id: string,
     *     name: string|null,
     *     has_api_key: bool,
     *     default_spotify_device_id: string|null,
     *     default_device_name: string|null
     * }
     */
    private function readerToArray(ReaderDevice $r): array
    {
        return [
            'id' => $r->getId(),
            'reader_id' => $r->getReaderId(),
            'name' => $r->getName(),
            'has_api_key' => $r->hasApiKey(),
            'default_spotify_device_id' => $r->getDefaultSpotifyDeviceId(),
            'default_device_name' => $r->getDefaultDeviceName(),
        ];
    }
}

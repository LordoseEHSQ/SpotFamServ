<?php

declare(strict_types=1);

namespace App\Module\Scan\Infrastructure\Http;

use App\Module\Scan\Application\ListScanEvents;
use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Application\ProcessReaderControl;
use App\Module\Scan\Application\ProcessScan;
use App\Module\Scan\Domain\ScanEvent;
use App\Module\Scan\Domain\ScanOutcome;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reader scan endpoint.
 * Request payload: { "reader_id": "optional", "card_uid": "required" }.
 * Reader auth (D-K1, Option B): if the addressed reader has its own API key, ONLY that
 * per-reader key is accepted. Otherwise we fall back to the global READER_API_KEY
 * (today's behaviour). If neither is configured, auth stays open (dev default).
 */
#[Route(path: '/readers', name: 'api_scan_', format: 'json')]
final class ScanController
{
    public function __construct(
        private readonly ProcessScan $processScan,
        private readonly ListScanEvents $listScanEvents,
        private readonly ProcessReaderControl $processReaderControl,
        private readonly ReaderDeviceRepositoryInterface $readerDevices,
        private readonly string $readerApiKey = '',
    ) {
    }

    #[Route(path: '/scan', name: 'scan', methods: ['POST'])]
    public function scan(Request $request): JsonResponse
    {
        $body = $this->parseBody($request);
        $readerId = isset($body['reader_id']) ? trim((string) $body['reader_id']) : '';

        if (!$this->validateReaderAuth($request, $readerId)) {
            return $this->unauthorized();
        }

        $cardUid = isset($body['card_uid']) ? trim((string) $body['card_uid']) : '';
        if ($cardUid === '') {
            return new JsonResponse(['outcome' => ScanOutcome::INVALID_REQUEST, 'message' => 'Missing card_uid.'], 400);
        }

        $result = ($this->processScan)($readerId, $cardUid);
        return new JsonResponse(['outcome' => $result->outcome, 'message' => $result->message]);
    }

    #[Route(path: '/next', name: 'next', methods: ['POST'])]
    public function next(Request $request): JsonResponse
    {
        return $this->control($request, ProcessReaderControl::ACTION_NEXT);
    }

    #[Route(path: '/previous', name: 'previous', methods: ['POST'])]
    public function previous(Request $request): JsonResponse
    {
        return $this->control($request, ProcessReaderControl::ACTION_PREVIOUS);
    }

    private function control(Request $request, string $action): JsonResponse
    {
        $body = $this->parseBody($request);
        $readerId = isset($body['reader_id']) ? trim((string) $body['reader_id']) : '';

        if (!$this->validateReaderAuth($request, $readerId)) {
            return $this->unauthorized();
        }

        $result = ($this->processReaderControl)($readerId, $action);
        $status = $result->outcome === ScanOutcome::SUCCESS ? 200 : 409;
        return new JsonResponse(['outcome' => $result->outcome, 'message' => $result->message], $status);
    }

    #[Route(path: '/scan-events', name: 'scan_events', methods: ['GET'])]
    public function scanEvents(Request $request): JsonResponse
    {
        $profileId = $request->query->getString('profile_id') ?: null;
        $limit = min(100, max(1, (int) $request->query->get('limit', '50')));
        $offset = max(0, (int) $request->query->get('offset', '0'));
        $events = ($this->listScanEvents)($limit, $offset, $profileId);
        $items = array_map(fn (ScanEvent $e) => [
            'id' => $e->getId(),
            'card_uid_raw' => $e->getCardUidRaw(),
            'outcome' => $e->getOutcome(),
            'created_at' => $e->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $events);
        return new JsonResponse(['items' => $items]);
    }

    /**
     * Authenticates a reader request (D-K1, Option B):
     *  - If the addressed reader has its own key hash, ONLY that per-reader key is
     *    accepted; the global key is intentionally rejected for it. This makes a
     *    compromised reader isolable: delete the key (fall back) or delete the reader (401).
     *  - Otherwise (reader has no key, or reader_id is empty/unknown) we fall back to
     *    the global READER_API_KEY, preserving today's behaviour.
     *  - If neither is configured, auth stays open (dev/MVP default).
     */
    private function validateReaderAuth(Request $request, string $readerId): bool
    {
        $presentedKey = $this->extractPresentedKey($request);

        $reader = $readerId !== '' ? $this->readerDevices->findByReaderId($readerId) : null;
        if ($reader !== null && $reader->hasApiKey()) {
            return $presentedKey !== '' && $reader->validateApiKey($presentedKey);
        }

        if ($this->readerApiKey === '') {
            return true;
        }

        return $presentedKey !== '' && hash_equals($this->readerApiKey, $presentedKey);
    }

    private function extractPresentedKey(Request $request): string
    {
        $key = $request->headers->get('X-API-Key');
        if ($key !== null && $key !== '') {
            return $key;
        }
        $auth = $request->headers->get('Authorization');
        if ($auth !== null && str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }
        return '';
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse([
            'outcome' => ScanOutcome::INVALID_REQUEST,
            'message' => 'Missing or invalid API key. Use X-API-Key or Authorization: Bearer.',
        ], 401);
    }

    /**
     * Parses the JSON body once, tolerating an empty body (control endpoints may
     * be called without a payload). Auth needs reader_id before the body is used.
     *
     * @return array<string, mixed>
     */
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

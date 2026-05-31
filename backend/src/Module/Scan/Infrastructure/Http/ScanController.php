<?php

declare(strict_types=1);

namespace App\Module\Scan\Infrastructure\Http;

use App\Module\Scan\Application\ListScanEvents;
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
 * Reader auth (MVP): if READER_API_KEY env is set, require X-API-Key or Authorization: Bearer <key>.
 */
#[Route(path: '/readers', name: 'api_scan_', format: 'json')]
final class ScanController
{
    public function __construct(
        private readonly ProcessScan $processScan,
        private readonly ListScanEvents $listScanEvents,
        private readonly ProcessReaderControl $processReaderControl,
        private readonly string $readerApiKey = '',
    ) {
    }

    #[Route(path: '/scan', name: 'scan', methods: ['POST'])]
    public function scan(Request $request): JsonResponse
    {
        if ($this->readerApiKey !== '' && !$this->validateReaderAuth($request)) {
            return new JsonResponse([
                'outcome' => ScanOutcome::INVALID_REQUEST,
                'message' => 'Missing or invalid API key. Use X-API-Key or Authorization: Bearer.',
            ], 401);
        }

        $body = $request->toArray();
        $readerId = isset($body['reader_id']) ? trim((string) $body['reader_id']) : '';
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
        if ($this->readerApiKey !== '' && !$this->validateReaderAuth($request)) {
            return new JsonResponse([
                'outcome' => ScanOutcome::INVALID_REQUEST,
                'message' => 'Missing or invalid API key. Use X-API-Key or Authorization: Bearer.',
            ], 401);
        }

        $body = $request->getContent() !== '' ? $request->toArray() : [];
        $readerId = isset($body['reader_id']) ? trim((string) $body['reader_id']) : '';

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

    private function validateReaderAuth(Request $request): bool
    {
        $key = $request->headers->get('X-API-Key');
        if ($key !== null && $key !== '') {
            return hash_equals($this->readerApiKey, $key);
        }
        $auth = $request->headers->get('Authorization');
        if ($auth !== null && str_starts_with($auth, 'Bearer ')) {
            return hash_equals($this->readerApiKey, trim(substr($auth, 7)));
        }
        return false;
    }
}

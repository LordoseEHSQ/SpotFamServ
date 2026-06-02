<?php

declare(strict_types=1);

namespace App\Module\Rfid\Infrastructure\Http;

use App\Module\Rfid\Application\LookupRfidCardByUid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Profile-independent lookup for the Scan-to-Create UX: given a (scanned) card
 * UID, report whether it is already assigned and to whom. Deliberately NOT under
 * /profiles/{id} because the answer is global (card_uid is unique system-wide).
 */
#[Route(path: '/rfid-cards/lookup', name: 'api_rfid_lookup', methods: ['GET'], format: 'json')]
final class RfidCardLookupController
{
    public function __construct(
        private readonly LookupRfidCardByUid $lookup,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $cardUid = trim((string) $request->query->get('card_uid', ''));
        if ($cardUid === '') {
            return new JsonResponse(['error' => 'card_uid is required.'], 400);
        }

        $result = ($this->lookup)($cardUid);

        return new JsonResponse([
            'status' => $result->status,
            'card_uid' => $result->cardUid,
            'profile_id' => $result->profileId,
            'profile_name' => $result->profileName,
            'label' => $result->label,
            'has_binding' => $result->hasBinding,
            'binding_name' => $result->bindingName,
        ]);
    }
}

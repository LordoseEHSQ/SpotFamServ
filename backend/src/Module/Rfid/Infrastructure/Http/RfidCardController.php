<?php

declare(strict_types=1);

namespace App\Module\Rfid\Infrastructure\Http;

use App\Module\Rfid\Application\CreateRfidCard;
use App\Module\Rfid\Application\DeleteRfidCard;
use App\Module\Rfid\Application\GetCardPlaylistBinding;
use App\Module\Rfid\Application\GetRfidCard;
use App\Module\Rfid\Application\ListRfidCardsWithBindings;
use App\Module\Rfid\Application\SetCardPlaylistBinding;
use App\Module\Rfid\Application\UpdateRfidCard;
use App\Module\Rfid\Domain\RfidCard;
use App\Module\Spotify\Domain\SpotifyPlaylistReference;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/profiles/{profileId}/rfid-cards', name: 'api_rfid_', format: 'json', requirements: ['profileId' => '%uuid_regex%'])]
final class RfidCardController
{
    public function __construct(
        private readonly ListRfidCardsWithBindings $listCardsWithBindings,
        private readonly GetRfidCard $getCard,
        private readonly CreateRfidCard $createCard,
        private readonly UpdateRfidCard $updateCard,
        private readonly DeleteRfidCard $deleteCard,
        private readonly GetCardPlaylistBinding $getBinding,
        private readonly SetCardPlaylistBinding $setBinding,
    ) {
    }

    #[Route(name: 'list', methods: ['GET'])]
    public function list(string $profileId): JsonResponse
    {
        $items = ($this->listCardsWithBindings)($profileId);
        return new JsonResponse([
            'items' => array_map(
                fn(array $item) => $this->cardToArray($item['card'], $item['binding']),
                $items,
            ),
        ]);
    }

    #[Route(path: '/{cardId}', name: 'get', methods: ['GET'], requirements: ['cardId' => '%uuid_regex%'])]
    public function get(string $profileId, string $cardId): JsonResponse
    {
        $card = ($this->getCard)($profileId, $cardId);
        return new JsonResponse($this->cardToArray($card));
    }

    #[Route(name: 'create', methods: ['POST'])]
    public function create(string $profileId, Request $request): JsonResponse
    {
        $body = $request->toArray();
        $cardUid = isset($body['card_uid']) ? trim((string) $body['card_uid']) : '';
        $label = isset($body['label']) ? trim((string) $body['label']) : null;
        if ($cardUid === '') {
            return new JsonResponse(['error' => 'card_uid is required.'], 400);
        }
        $card = ($this->createCard)($profileId, $cardUid, $label ?: null);
        return new JsonResponse($this->cardToArray($card), 201);
    }

    #[Route(path: '/{cardId}', name: 'update', methods: ['PUT'], requirements: ['cardId' => '%uuid_regex%'])]
    public function update(string $profileId, string $cardId, Request $request): JsonResponse
    {
        $body = $request->toArray();
        $label = isset($body['label']) ? trim((string) $body['label']) : null;
        $card = ($this->updateCard)($profileId, $cardId, $label);
        return new JsonResponse($this->cardToArray($card));
    }

    #[Route(path: '/{cardId}', name: 'delete', methods: ['DELETE'], requirements: ['cardId' => '%uuid_regex%'])]
    public function delete(string $profileId, string $cardId): JsonResponse
    {
        ($this->deleteCard)($profileId, $cardId);
        return new JsonResponse(null, 204);
    }

    #[Route(path: '/{cardId}/binding', name: 'get_binding', methods: ['GET'], requirements: ['cardId' => '%uuid_regex%'])]
    public function getBinding(string $profileId, string $cardId): JsonResponse
    {
        $ref = ($this->getBinding)($profileId, $cardId);
        return new JsonResponse($this->playlistRefToArray($ref));
    }

    #[Route(path: '/{cardId}/binding', name: 'set_binding', methods: ['PUT'], requirements: ['cardId' => '%uuid_regex%'])]
    public function setBinding(string $profileId, string $cardId, Request $request): JsonResponse
    {
        $body = $request->toArray();
        $refId = isset($body['spotify_playlist_reference_id'])
            ? trim((string) $body['spotify_playlist_reference_id'])
            : null;
        if ($refId === '') {
            $refId = null;
        }
        ($this->setBinding)($profileId, $cardId, $refId);
        return new JsonResponse(null, 204);
    }

    private function cardToArray(RfidCard $c, ?SpotifyPlaylistReference $binding = null): array
    {
        return [
            'id' => $c->getId(),
            'card_uid' => $c->getCardUid(),
            'label' => $c->getLabel(),
            'binding' => $binding !== null
                ? ['id' => $binding->getId(), 'name' => $binding->getName()]
                : null,
        ];
    }

    private function playlistRefToArray(?SpotifyPlaylistReference $ref): ?array
    {
        if ($ref === null) {
            return null;
        }
        return [
            'id' => $ref->getId(),
            'name' => $ref->getName(),
            'spotify_playlist_id' => $ref->getSpotifyPlaylistId(),
        ];
    }
}

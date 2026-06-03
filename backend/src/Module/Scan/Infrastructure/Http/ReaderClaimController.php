<?php

declare(strict_types=1);

namespace App\Module\Scan\Infrastructure\Http;

use App\Module\Scan\Application\CreateReaderClaim;
use App\Module\Scan\Application\GetReaderClaimStatus;
use App\Module\Scan\Application\ReaderClaimException;
use App\Module\Scan\Application\RedeemReaderClaim;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/readers/claims', name: 'api_reader_claims_', format: 'json')]
final readonly class ReaderClaimController
{
    public function __construct(
        private CreateReaderClaim $createClaim,
        private GetReaderClaimStatus $claimStatus,
        private RedeemReaderClaim $redeemClaim,
    ) {
    }

    #[Route(name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = $this->parseBody($request);
        $result = ($this->createClaim)(
            isset($body['reader_name']) ? (string) $body['reader_name'] : null,
            isset($body['fw_channel']) ? (string) $body['fw_channel'] : null,
        );

        return new JsonResponse([
            'claim_code' => $result->claimCode,
            'expires_at' => $result->expiresAt->format(\DateTimeInterface::ATOM),
            'backend_url' => $request->getSchemeAndHttpHost(),
            'fw_channel' => $result->firmwareChannel,
        ], Response::HTTP_CREATED);
    }

    #[Route(path: '/{claimCode}', name: 'status', methods: ['GET'], requirements: ['claimCode' => '[A-Za-z0-9\\- ]+'])]
    public function status(string $claimCode): JsonResponse
    {
        try {
            $claim = ($this->claimStatus)($claimCode);
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            return new JsonResponse([
                'status' => $claim->status($now),
                'expires_at' => $claim->getExpiresAt()->format(\DateTimeInterface::ATOM),
                'reader_id' => $claim->getReaderId(),
                'fw_channel' => $claim->getFirmwareChannel(),
            ]);
        } catch (ReaderClaimException $e) {
            return $this->claimError($e);
        }
    }

    #[Route(path: '/{claimCode}/activate', name: 'activate', methods: ['POST'], requirements: ['claimCode' => '[A-Za-z0-9\\- ]+'])]
    public function activate(string $claimCode, Request $request): JsonResponse
    {
        try {
            $body = $this->parseBody($request);
            $result = ($this->redeemClaim)(
                $claimCode,
                isset($body['device_nonce']) ? (string) $body['device_nonce'] : '',
                isset($body['board']) ? (string) $body['board'] : '',
                isset($body['firmware_version']) ? (string) $body['firmware_version'] : '',
            );

            return new JsonResponse([
                'reader_id' => $result->readerId,
                'api_key' => $result->apiKey,
                'fw_channel' => $result->firmwareChannel,
            ], Response::HTTP_CREATED);
        } catch (ReaderClaimException $e) {
            return $this->claimError($e);
        }
    }

    private function claimError(ReaderClaimException $e): JsonResponse
    {
        return new JsonResponse([
            'error' => $e->errorCode,
            'message' => $e->getMessage(),
        ], $e->statusCode);
    }

    /**
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

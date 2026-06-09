<?php

declare(strict_types=1);

namespace App\Tests\Module\Scan\Infrastructure\Http;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Scan\Application\CreateReaderClaim;
use App\Module\Scan\Application\GetReaderClaimStatus;
use App\Module\Scan\Application\Port\ReaderClaimRepositoryInterface;
use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Application\ReaderClaimCode;
use App\Module\Scan\Application\RedeemReaderClaim;
use App\Module\Scan\Domain\ReaderClaim;
use App\Module\Scan\Domain\ReaderDevice;
use App\Module\Scan\Infrastructure\Http\ReaderClaimController;
use App\Shared\Application\Port\TransactionRunnerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ReaderClaimControllerTest extends TestCase
{
    private InMemoryReaderClaimRepository $claims;
    private InMemoryReaderDeviceRepository $readers;
    private ReaderClaimController $controller;

    protected function setUp(): void
    {
        $this->claims = new InMemoryReaderClaimRepository();
        $this->readers = new InMemoryReaderDeviceRepository();
        $activity = new InMemoryActivityLogRepository();
        $tx = new InlineTransactionRunner();

        $this->controller = new ReaderClaimController(
            new CreateReaderClaim($this->claims, $activity),
            new GetReaderClaimStatus($this->claims),
            new RedeemReaderClaim($this->claims, $this->readers, $activity, $tx),
        );
    }

    public function test_create_returns_plain_claim_once_with_backend_url(): void
    {
        $response = $this->controller->create($this->jsonRequest(['reader_name' => 'Kitchen', 'fw_channel' => 'stable']));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $payload['claim_code']);
        $this->assertSame('http://localhost', $payload['backend_url']);
        $this->assertSame('stable', $payload['fw_channel']);
        $this->assertCount(1, $this->claims->all);
    }

    public function test_activate_redeems_claim_and_returns_plain_api_key_once(): void
    {
        $claimCode = $this->createClaim();

        $response = $this->controller->activate($claimCode, $this->jsonRequest([
            'device_nonce' => 'esp-mac-1',
            'board' => 'esp32-wroom-32',
            'firmware_version' => '0.1.0',
        ]));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertStringStartsWith('esp-', $payload['reader_id']);
        $this->assertNotSame('', $payload['api_key']);
        $this->assertSame('stable', $payload['fw_channel']);

        $reader = $this->readers->findByReaderId($payload['reader_id']);
        $this->assertNotNull($reader);
        $this->assertTrue($reader->validateApiKey($payload['api_key']));
    }

    public function test_status_becomes_claimed_after_activation(): void
    {
        $claimCode = $this->createClaim();
        $this->controller->activate($claimCode, $this->jsonRequest([
            'device_nonce' => 'esp-mac-1',
            'board' => 'esp32-wroom-32',
            'firmware_version' => '0.1.0',
        ]));

        $response = $this->controller->status($claimCode);
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(ReaderClaim::STATUS_CLAIMED, $payload['status']);
        $this->assertNotEmpty($payload['reader_id']);
    }

    public function test_claim_cannot_be_reused(): void
    {
        $claimCode = $this->createClaim();
        $request = $this->jsonRequest([
            'device_nonce' => 'esp-mac-1',
            'board' => 'esp32-wroom-32',
            'firmware_version' => '0.1.0',
        ]);
        $this->controller->activate($claimCode, $request);

        $response = $this->controller->activate($claimCode, $request);
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('claim_already_used', $payload['error']);
    }

    public function test_expired_claim_returns_410(): void
    {
        $claim = new ReaderClaim(
            ReaderClaimCode::hash('ABCDEFG2'),
            new \DateTimeImmutable('-1 minute', new \DateTimeZone('UTC')),
            null,
            'stable',
        );
        $this->claims->save($claim);

        $response = $this->controller->activate('ABCDEFG2', $this->jsonRequest([
            'device_nonce' => 'esp-mac-1',
            'board' => 'esp32-wroom-32',
            'firmware_version' => '0.1.0',
        ]));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Response::HTTP_GONE, $response->getStatusCode());
        $this->assertSame('expired_claim', $payload['error']);
    }

    public function test_unsupported_board_counts_attempts_and_then_rate_limits(): void
    {
        $claimCode = $this->createClaim();

        for ($i = 0; $i < 5; $i++) {
            $response = $this->controller->activate($claimCode, $this->jsonRequest([
                'device_nonce' => 'esp-mac-1',
                'board' => 'esp8266',
                'firmware_version' => '0.1.0',
            ]));
            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        }

        $response = $this->controller->activate($claimCode, $this->jsonRequest([
            'device_nonce' => 'esp-mac-1',
            'board' => 'esp8266',
            'firmware_version' => '0.1.0',
        ]));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertSame('too_many_attempts', $payload['error']);
    }

    private function createClaim(): string
    {
        $response = $this->controller->create($this->jsonRequest([]));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        return $payload['claim_code'];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonRequest(array $body): Request
    {
        return Request::create('http://localhost/api/v1/readers/claims', 'POST', [], [], [], [], json_encode($body, JSON_THROW_ON_ERROR));
    }
}

final class InMemoryReaderClaimRepository implements ReaderClaimRepositoryInterface
{
    /** @var array<string, ReaderClaim> */
    public array $all = [];

    public function findByCodeHash(string $claimCodeHash): ?ReaderClaim
    {
        return $this->all[$claimCodeHash] ?? null;
    }

    public function save(ReaderClaim $claim): void
    {
        $this->all[$claim->getClaimCodeHash()] = $claim;
    }

    public function deleteByReaderId(string $readerId): void
    {
        $this->all = array_filter($this->all, static fn (ReaderClaim $c) => $c->getReaderId() !== $readerId);
    }
}

final class InMemoryReaderDeviceRepository implements ReaderDeviceRepositoryInterface
{
    /** @var array<string, ReaderDevice> */
    private array $all = [];

    public function findByReaderId(string $readerId): ?ReaderDevice
    {
        return $this->all[$readerId] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->all);
    }

    public function save(ReaderDevice $device): void
    {
        $this->all[$device->getReaderId()] = $device;
    }

    public function delete(ReaderDevice $device): void
    {
        unset($this->all[$device->getReaderId()]);
    }
}

final class InMemoryActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function findRecent(?\Symfony\Component\Uid\Uuid $profileId = null, ?string $severity = null, int $limit = 50, int $offset = 0): array
    {
        return [];
    }

    public function countRecent(?\Symfony\Component\Uid\Uuid $profileId = null, ?string $severity = null): int
    {
        return 0;
    }

    public function append(ActivityLog $entry): void
    {
    }
}

final class InlineTransactionRunner implements TransactionRunnerInterface
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Scan\Application\Port\ReaderClaimRepositoryInterface;
use App\Module\Scan\Domain\ReaderClaim;

final readonly class CreateReaderClaim
{
    private const TTL_SECONDS = 600;
    private const MAX_GENERATION_ATTEMPTS = 5;

    public function __construct(
        private ReaderClaimRepositoryInterface $claims,
        private ActivityLogRepositoryInterface $activityLog,
    ) {
    }

    public function __invoke(?string $readerName, ?string $firmwareChannel): CreateReaderClaimResult
    {
        $fwChannel = $this->normalizeFirmwareChannel($firmwareChannel);

        for ($attempt = 0; $attempt < self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            $plainCode = ReaderClaimCode::generate();
            $hash = ReaderClaimCode::hash($plainCode);
            if ($this->claims->findByCodeHash($hash) === null) {
                $expiresAt = new \DateTimeImmutable('+' . self::TTL_SECONDS . ' seconds', new \DateTimeZone('UTC'));
                $claim = new ReaderClaim($hash, $expiresAt, $this->normalizeReaderName($readerName), $fwChannel);
                $this->claims->save($claim);
                $this->activityLog->append(new ActivityLog(
                    ActivityLog::TYPE_READER_CLAIM_CREATED,
                    'Reader claim created.',
                    ActivityLog::SEVERITY_INFO,
                    null,
                    'reader_claim',
                    $claim->getId(),
                    ['fw_channel' => $fwChannel],
                ));

                return new CreateReaderClaimResult($plainCode, $expiresAt, $fwChannel);
            }
        }

        throw new \RuntimeException('Failed to generate unique reader claim code.');
    }

    private function normalizeReaderName(?string $readerName): ?string
    {
        $name = trim((string) $readerName);
        return $name === '' ? null : substr($name, 0, 255);
    }

    private function normalizeFirmwareChannel(?string $firmwareChannel): string
    {
        $channel = strtolower(trim((string) $firmwareChannel));
        return $channel === '' ? 'stable' : substr($channel, 0, 32);
    }
}

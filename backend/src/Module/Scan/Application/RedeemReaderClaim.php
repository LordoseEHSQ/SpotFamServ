<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use App\Module\ActivityLog\Application\Port\ActivityLogRepositoryInterface;
use App\Module\ActivityLog\Domain\ActivityLog;
use App\Module\Scan\Application\Port\ReaderClaimRepositoryInterface;
use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Domain\ReaderDevice;
use App\Shared\Application\Port\TransactionRunnerInterface;

final readonly class RedeemReaderClaim
{
    private const SUPPORTED_BOARD = 'esp32-wroom-32';
    private const KEY_BYTES = 24;

    public function __construct(
        private ReaderClaimRepositoryInterface $claims,
        private ReaderDeviceRepositoryInterface $readers,
        private ActivityLogRepositoryInterface $activityLog,
        private TransactionRunnerInterface $transactionRunner,
    ) {
    }

    public function __invoke(string $claimCode, string $deviceNonce, string $board, string $firmwareVersion): RedeemReaderClaimResult
    {
        return $this->transactionRunner->run(function () use ($claimCode, $deviceNonce, $board, $firmwareVersion): RedeemReaderClaimResult {
            $claim = $this->loadClaim($claimCode);
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

            if ($claim->getUsedAt() !== null) {
                throw ReaderClaimException::alreadyUsed();
            }
            if ($claim->getExpiresAt() <= $now) {
                throw ReaderClaimException::expiredClaim();
            }
            if ($claim->hasTooManyActivationAttempts()) {
                throw ReaderClaimException::tooManyAttempts();
            }

            $deviceNonce = trim($deviceNonce);
            $board = strtolower(trim($board));
            $firmwareVersion = trim($firmwareVersion);
            if ($deviceNonce === '' || $board === '' || $firmwareVersion === '') {
                $this->recordFailedAttempt($claim, 'invalid_request');
                throw ReaderClaimException::invalidRequest('device_nonce, board and firmware_version are required.');
            }

            if ($board !== self::SUPPORTED_BOARD) {
                $this->recordFailedAttempt($claim, 'unsupported_board');
                throw ReaderClaimException::unsupportedBoard();
            }

            $readerId = $this->generateReaderId($deviceNonce);
            while ($this->readers->findByReaderId($readerId) !== null) {
                $readerId = $this->generateReaderId($deviceNonce . random_bytes(4));
            }

            $plainKey = bin2hex(random_bytes(self::KEY_BYTES));
            $reader = new ReaderDevice($readerId, $claim->getReaderName());
            $reader->setApiKey($plainKey);
            $this->readers->save($reader);

            $claim->markUsed($readerId);
            $this->claims->save($claim);

            $this->activityLog->append(new ActivityLog(
                ActivityLog::TYPE_READER_CLAIM_REDEEMED,
                'Reader claim redeemed.',
                ActivityLog::SEVERITY_INFO,
                null,
                'reader_device',
                $readerId,
                [
                    'board' => $board,
                    'firmware_version' => $firmwareVersion,
                    'fw_channel' => $claim->getFirmwareChannel(),
                ],
            ));

            return new RedeemReaderClaimResult($readerId, $plainKey, $claim->getFirmwareChannel());
        });
    }

    private function loadClaim(string $claimCode): \App\Module\Scan\Domain\ReaderClaim
    {
        if (!ReaderClaimCode::isValid($claimCode)) {
            throw ReaderClaimException::invalidRequest('Invalid claim code format.');
        }

        $claim = $this->claims->findByCodeHash(ReaderClaimCode::hash($claimCode));
        if ($claim === null) {
            throw ReaderClaimException::unknownClaim();
        }

        return $claim;
    }

    private function recordFailedAttempt(\App\Module\Scan\Domain\ReaderClaim $claim, string $reason): void
    {
        $claim->recordFailedActivationAttempt();
        $this->claims->save($claim);
        $this->activityLog->append(new ActivityLog(
            ActivityLog::TYPE_READER_CLAIM_FAILED,
            'Reader claim activation failed.',
            ActivityLog::SEVERITY_WARNING,
            null,
            'reader_claim',
            $claim->getId(),
            ['reason' => $reason, 'attempts' => $claim->getActivationAttempts()],
        ));
    }

    private function generateReaderId(string $deviceNonce): string
    {
        return 'esp-' . substr(hash('sha256', $deviceNonce . random_bytes(8)), 0, 12);
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Shared\Application\Exception\NotFoundException;

/**
 * Generates (or rotates) a dedicated, per-reader API key (hardening stage 1, D-K1).
 * A cryptographically random plain key is created, only its hash is persisted on the
 * reader, and the plain key is returned exactly once for the operator to provision.
 */
final readonly class GenerateReaderApiKey
{
    private const KEY_BYTES = 24;

    public function __construct(
        private ReaderDeviceRepositoryInterface $repository,
    ) {
    }

    /**
     * @return string the plain API key, returned only once and never stored
     */
    public function __invoke(string $readerId): string
    {
        $reader = $this->repository->findByReaderId($readerId);
        if ($reader === null) {
            throw new NotFoundException('Reader not found. It registers automatically on first scan.');
        }

        $plainKey = bin2hex(random_bytes(self::KEY_BYTES));
        $reader->setApiKey($plainKey);
        $this->repository->save($reader);

        return $plainKey;
    }
}

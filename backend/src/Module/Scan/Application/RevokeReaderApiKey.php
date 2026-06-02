<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use App\Module\Scan\Application\Port\ReaderDeviceRepositoryInterface;
use App\Module\Scan\Domain\ReaderDevice;
use App\Shared\Application\Exception\NotFoundException;

/**
 * Removes a reader's dedicated API key so authentication falls back to the global
 * READER_API_KEY again. Used to neutralise a compromised per-reader key (D-K1).
 */
final readonly class RevokeReaderApiKey
{
    public function __construct(
        private ReaderDeviceRepositoryInterface $repository,
    ) {
    }

    public function __invoke(string $readerId): ReaderDevice
    {
        $reader = $this->repository->findByReaderId($readerId);
        if ($reader === null) {
            throw new NotFoundException('Reader not found. It registers automatically on first scan.');
        }

        $reader->clearApiKey();
        $this->repository->save($reader);

        return $reader;
    }
}

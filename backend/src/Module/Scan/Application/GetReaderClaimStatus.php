<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use App\Module\Scan\Application\Port\ReaderClaimRepositoryInterface;
use App\Module\Scan\Domain\ReaderClaim;

final readonly class GetReaderClaimStatus
{
    public function __construct(
        private ReaderClaimRepositoryInterface $claims,
    ) {
    }

    public function __invoke(string $claimCode): ReaderClaim
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
}

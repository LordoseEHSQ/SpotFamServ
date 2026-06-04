<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application;

use App\Module\Provisioning\Application\Port\FlashJobRepositoryInterface;
use App\Module\Provisioning\Domain\FlashJob;

/**
 * Gibt einen einzelnen Flash-Job zurück (GET /api/v1/provisioning/jobs/{jobId}).
 */
final readonly class GetJob
{
    public function __construct(
        private FlashJobRepositoryInterface $jobs,
    ) {
    }

    public function __invoke(string $jobId): FlashJob
    {
        $job = $this->jobs->findById($jobId);
        if ($job === null) {
            throw ProvisioningException::jobNotFound($jobId);
        }

        return $job;
    }
}

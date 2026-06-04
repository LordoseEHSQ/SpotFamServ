<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application;

use App\Module\Provisioning\Application\Port\FlashArtifactRepositoryInterface;
use App\Module\Provisioning\Domain\FlashArtifact;

/**
 * Listet alle registrierten Firmware-Artefakte (GET /api/v1/provisioning/artifacts).
 *
 * @return list<FlashArtifact>
 */
final readonly class ListArtifacts
{
    public function __construct(
        private FlashArtifactRepositoryInterface $artifacts,
    ) {
    }

    /** @return list<FlashArtifact> */
    public function __invoke(): array
    {
        return $this->artifacts->findAll();
    }
}

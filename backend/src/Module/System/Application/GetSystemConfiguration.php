<?php

declare(strict_types=1);

namespace App\Module\System\Application;

use App\Module\System\Application\Port\SystemConfigurationRepositoryInterface;
use App\Module\System\Domain\SystemConfiguration;

final readonly class GetSystemConfiguration
{
    public function __construct(
        private SystemConfigurationRepositoryInterface $repository,
        private string $envFrontendUrl,
    ) {
    }

    /**
     * Liefert die aktive DB-Konfiguration oder ein transientes, aus Env/Defaults
     * vorbefülltes Objekt (Quelle markiert).
     *
     * @return array{config: SystemConfiguration, source: 'db'|'env'}
     */
    public function __invoke(): array
    {
        $config = $this->repository->findActive();
        if ($config !== null) {
            return ['config' => $config, 'source' => 'db'];
        }

        $fromEnv = new SystemConfiguration();
        if ($this->envFrontendUrl !== '') {
            $fromEnv->setFrontendUrl($this->envFrontendUrl);
        }

        return ['config' => $fromEnv, 'source' => 'env'];
    }
}

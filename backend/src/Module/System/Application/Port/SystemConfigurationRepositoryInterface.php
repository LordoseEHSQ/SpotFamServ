<?php

declare(strict_types=1);

namespace App\Module\System\Application\Port;

use App\Module\System\Domain\SystemConfiguration;

interface SystemConfigurationRepositoryInterface
{
    public function findActive(): ?SystemConfiguration;

    public function save(SystemConfiguration $config): void;
}

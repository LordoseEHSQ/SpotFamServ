<?php

declare(strict_types=1);

namespace App\Module\Provisioning\Application;

final readonly class DetectDeviceResult
{
    public function __construct(
        public string $deviceId,
        public string $status,
        /** true = Gerät wurde neu angelegt, false = bestehendes Gerät aktualisiert */
        public bool $isNew,
    ) {
    }
}

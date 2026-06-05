<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Domain;

/**
 * Storage quota guard (HTTP 507 Insufficient Storage): the shared audio area is at
 * or over its configured size limit. Enforced before extraction (current total) and
 * after storing (the produced file would exceed the limit → it is deleted again).
 */
final class StorageQuotaExceededException extends \RuntimeException
{
}

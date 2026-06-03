<?php

declare(strict_types=1);

namespace App\Module\AudioExtractor\Domain;

/**
 * Client error (HTTP 422): the request was rejected before any subprocess started,
 * e.g. unsupported scheme, unknown format, or invalid bitrate.
 */
final class InvalidMediaRequestException extends \RuntimeException
{
}

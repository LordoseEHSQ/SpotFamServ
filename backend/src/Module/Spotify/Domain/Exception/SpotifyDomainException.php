<?php

declare(strict_types=1);

namespace App\Module\Spotify\Domain\Exception;

/**
 * Base class for all Spotify domain exceptions.
 * Must NOT contain any HTTP-specific details.
 */
abstract class SpotifyDomainException extends \RuntimeException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

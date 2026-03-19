<?php

declare(strict_types=1);

namespace App\Module\Spotify\Domain\Exception;

/**
 * Concrete exception for generic Spotify Web API errors (non-auth, non-scope).
 * Carries the HTTP status code for error mapping at the infrastructure boundary.
 */
class SpotifyApiException extends SpotifyDomainException
{
    public function __construct(string $message, private readonly int $httpStatus = 500)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

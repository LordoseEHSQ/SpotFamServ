<?php

declare(strict_types=1);

namespace App\Module\Spotify\Domain\Exception;

/**
 * Required Spotify OAuth scope missing or insufficient.
 */
final class SpotifyScopeMissingException extends SpotifyDomainException
{
    public function __construct(string $message = 'Spotify scope missing or insufficient.')
    {
        parent::__construct($message);
    }
}

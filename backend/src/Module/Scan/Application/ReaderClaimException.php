<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

use Symfony\Component\HttpFoundation\Response;

final class ReaderClaimException extends \RuntimeException
{
    private function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode,
    ) {
        parent::__construct($message);
    }

    public static function invalidRequest(string $message): self
    {
        return new self('invalid_request', $message, Response::HTTP_BAD_REQUEST);
    }

    public static function unknownClaim(): self
    {
        return new self('unknown_claim', 'Claim code not found.', Response::HTTP_NOT_FOUND);
    }

    public static function expiredClaim(): self
    {
        return new self('expired_claim', 'Claim code expired.', Response::HTTP_GONE);
    }

    public static function alreadyUsed(): self
    {
        return new self('claim_already_used', 'Claim code already used.', Response::HTTP_CONFLICT);
    }

    public static function unsupportedBoard(): self
    {
        return new self('unsupported_board', 'Board is not supported for this firmware channel.', Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public static function tooManyAttempts(): self
    {
        return new self('too_many_attempts', 'Too many activation attempts for this claim.', Response::HTTP_TOO_MANY_REQUESTS);
    }
}

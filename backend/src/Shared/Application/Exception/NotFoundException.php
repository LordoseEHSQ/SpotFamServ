<?php

declare(strict_types=1);

namespace App\Shared\Application\Exception;

use Symfony\Component\HttpFoundation\Response;

final class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Resource not found.')
    {
        parent::__construct($message, 0, null, Response::HTTP_NOT_FOUND);
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Admin\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Liefert 401 JSON statt eines Login-Redirects (API-konforme Entry-Point-Antwort).
 */
final class AuthEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => 'unauthenticated'], Response::HTTP_UNAUTHORIZED);
    }
}

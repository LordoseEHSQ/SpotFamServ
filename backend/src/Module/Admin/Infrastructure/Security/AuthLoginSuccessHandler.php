<?php

declare(strict_types=1);

namespace App\Module\Admin\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Sendet 204 No Content nach erfolgreichem json_login (Session-Cookie wird automatisch gesetzt).
 */
final class AuthLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}

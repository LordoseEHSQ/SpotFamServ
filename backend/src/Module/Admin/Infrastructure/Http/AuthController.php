<?php

declare(strict_types=1);

namespace App\Module\Admin\Infrastructure\Http;

use App\Module\Admin\Domain\AdminUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(path: '/auth', name: 'api_auth_', format: 'json')]
final class AuthController
{
    public function __construct(
        private readonly string $csrfCookieName = 'XSRF-TOKEN',
    ) {
    }

    /**
     * GET /api/v1/auth/csrf
     *
     * Setzt ein NICHT-HttpOnly Double-Submit-CSRF-Cookie (XSRF-TOKEN) und antwortet 204.
     * Das Frontend liest dieses Cookie und spiegelt es bei mutierenden Requests als
     * Header X-XSRF-TOKEN. Public (vor Login erreichbar).
     */
    #[Route(path: '/csrf', name: 'csrf', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/auth/csrf',
        summary: 'Setzt das CSRF-Double-Submit-Cookie (XSRF-TOKEN)',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 204, description: 'CSRF-Cookie gesetzt'),
        ],
    )]
    public function csrf(Request $request): Response
    {
        $token = bin2hex(random_bytes(32));

        $cookie = Cookie::create($this->csrfCookieName)
            ->withValue($token)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(false)
            ->withSameSite(Cookie::SAMESITE_LAX);

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * GET /api/v1/auth/me
     *
     * Liefert Username und Rollen des eingeloggten Admins.
     * Catch-all-Regel → 401, wenn keine gültige Session vorhanden.
     */
    #[Route(path: '/me', name: 'me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Gibt den eingeloggten Admin zurück',
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Eingeloggter Admin',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                ]),
            ),
            new OA\Response(response: 401, description: 'Nicht eingeloggt'),
        ],
    )]
    public function me(#[CurrentUser] ?AdminUser $user): JsonResponse
    {
        if ($user === null) {
            return new JsonResponse(['error' => 'unauthenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'username' => $user->getUsername(),
            'roles'    => $user->getRoles(),
        ]);
    }

    /**
     * POST /api/v1/auth/login
     *
     * Route muss existieren, damit der Router kein 404 wirft.
     * Die Firewall (json_login) fängt diese Route ab – dieser Body wird nie erreicht.
     */
    #[Route(path: '/login', name: 'login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Login (json_login; wird von der Firewall abgefangen)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'username', type: 'string'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
            ]),
        ),
        responses: [
            new OA\Response(response: 204, description: 'Login erfolgreich (Set-Cookie)'),
            new OA\Response(response: 401, description: 'Ungültige Credentials'),
        ],
    )]
    public function login(): never
    {
        throw new \LogicException('json_login-Firewall fängt diesen Endpunkt ab – Controller-Body darf nie erreicht werden.');
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Route muss existieren; der Firewall-Logout-Listener fängt sie ab.
     * Response wird durch AuthLogoutSubscriber auf 204 gesetzt.
     */
    #[Route(path: '/logout', name: 'logout', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Logout (Session invalidieren)',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 204, description: 'Logout erfolgreich'),
        ],
    )]
    public function logout(): never
    {
        throw new \LogicException('Firewall-Logout-Listener fängt diesen Endpunkt ab – Controller-Body darf nie erreicht werden.');
    }
}

<?php

declare(strict_types=1);

namespace App\Module\Admin\Infrastructure\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Double-Submit-CSRF-Schutz für die cookie-basierte Session-Auth (Enterprise-Security).
 *
 * Bei mutierenden Methoden (POST/PUT/PATCH/DELETE) unter ^/api/v1 muss der Header
 * X-XSRF-TOKEN exakt dem Cookie XSRF-TOKEN entsprechen (hash_equals). Sonst 403.
 *
 * AUSNAHMEN (kein Cookie-Auth, sondern X-API-Key/OAuth → kein CSRF):
 * - Maschinen-Endpunkte (Reader/Agent) – per Pfad-Whitelist.
 * - GET /api/v1/auth/csrf (Token-Ausgabe selbst).
 * - Requests OHNE Session-Cookie (reine X-API-Key-Maschinen-Requests).
 *
 * Erzwungen wird CSRF nur, wenn ein Session-Cookie vorhanden ist ODER es der
 * Login-Endpunkt ist (dort existiert noch keine Session, der Schutz ist aber Pflicht).
 *
 * Läuft mit Priorität 9 (vor dem Firewall-Listener bei 8), damit ein CSRF-Fehler
 * vor der Authentifizierung abgewiesen wird.
 *
 * @phpstan-type MachinePattern non-empty-string
 */
final class CsrfSubscriber implements EventSubscriberInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    private const LOGIN_PATH = '/api/v1/auth/login';

    /**
     * Maschinen-Endpunkte, die per X-API-Key/OAuth geschützt sind und KEIN CSRF verlangen.
     * Muss konsistent zur public-access_control-Liste (security.yaml) gehalten werden.
     *
     * @var list<string>
     */
    private const MACHINE_PATTERNS = [
        '#^/api/v1/auth/csrf$#',
        '#^/api/v1/spotify/callback$#',
        '#^/api/v1/readers/scan$#',
        '#^/api/v1/readers/next$#',
        '#^/api/v1/readers/previous$#',
        '#^/api/v1/readers/claims/[^/]+/activate$#',
        '#^/api/v1/readers/firmware/manifest$#',
        '#^/api/v1/provisioning/devices/detect$#',
        '#^/api/v1/provisioning/jobs/next$#',
        '#^/api/v1/provisioning/jobs/[^/]+/status$#',
    ];

    public function __construct(
        private readonly string $sessionCookieName = 'SPOTFAM_SESSID',
        private readonly string $csrfCookieName = 'XSRF-TOKEN',
        private readonly string $csrfHeaderName = 'X-XSRF-TOKEN',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 9]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/v1')) {
            return;
        }

        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return;
        }

        if (!$this->shouldEnforce($request, $path)) {
            return;
        }

        $headerToken = $request->headers->get($this->csrfHeaderName);
        $cookieToken = $request->cookies->get($this->csrfCookieName);

        if (!is_string($headerToken) || !is_string($cookieToken) || $cookieToken === '' || !hash_equals($cookieToken, $headerToken)) {
            $event->setResponse(new JsonResponse(['error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN));
        }
    }

    private function shouldEnforce(Request $request, string $path): bool
    {
        // Login: CSRF Pflicht, auch ohne bestehende Session.
        if ($path === self::LOGIN_PATH) {
            return true;
        }

        // Maschinen-Endpunkte: nie CSRF (X-API-Key/OAuth).
        foreach (self::MACHINE_PATTERNS as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return false;
            }
        }

        // Reine Maschinen-Requests ohne Session-Cookie: kein CSRF.
        return $request->cookies->has($this->sessionCookieName);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Module\Admin\Infrastructure\Security;

use App\Module\Admin\Infrastructure\Security\CsrfSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class CsrfSubscriberTest extends TestCase
{
    private const SESSION_COOKIE = 'SPOTFAM_SESSID';
    private const CSRF_COOKIE = 'XSRF-TOKEN';
    private const CSRF_HEADER = 'X-XSRF-TOKEN';

    private CsrfSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new CsrfSubscriber(self::SESSION_COOKIE, self::CSRF_COOKIE, self::CSRF_HEADER);
    }

    /**
     * @param array<string,string> $cookies
     * @param array<string,string> $headers
     */
    private function dispatch(string $method, string $uri, array $cookies = [], array $headers = []): RequestEvent
    {
        $request = Request::create($uri, $method, [], $cookies);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        return $event;
    }

    public function testValidTokenPasses(): void
    {
        $event = $this->dispatch(
            'POST',
            '/api/v1/provisioning/artifacts',
            [self::SESSION_COOKIE => 'sess', self::CSRF_COOKIE => 'tok123'],
            [self::CSRF_HEADER => 'tok123'],
        );

        $this->assertNull($event->getResponse());
    }

    public function testMismatchWithSessionReturns403(): void
    {
        $event = $this->dispatch(
            'POST',
            '/api/v1/provisioning/artifacts',
            [self::SESSION_COOKIE => 'sess', self::CSRF_COOKIE => 'tok123'],
            [self::CSRF_HEADER => 'WRONG'],
        );

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('invalid_csrf', (string) $response->getContent());
    }

    public function testMissingHeaderWithSessionReturns403(): void
    {
        $event = $this->dispatch(
            'POST',
            '/api/v1/profiles',
            [self::SESSION_COOKIE => 'sess', self::CSRF_COOKIE => 'tok123'],
        );

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testMachinePathSkipped(): void
    {
        // Reader-Scan: X-API-Key-Maschine, kein CSRF – auch ohne Tokens kein 403.
        $event = $this->dispatch('POST', '/api/v1/readers/scan', [], ['X-API-Key' => 'machine-key']);

        $this->assertNull($event->getResponse());
    }

    public function testAgentJobStatusSkipped(): void
    {
        $event = $this->dispatch('POST', '/api/v1/provisioning/jobs/abc-123/status', [], ['X-API-Key' => 'agent-key']);

        $this->assertNull($event->getResponse());
    }

    public function testNoSessionCookieSkipped(): void
    {
        // Mutierender Request ohne Session-Cookie (reiner X-API-Key) → kein CSRF erzwungen.
        $event = $this->dispatch('POST', '/api/v1/devices/discover', [], ['X-API-Key' => 'key']);

        $this->assertNull($event->getResponse());
    }

    public function testGetMethodSkipped(): void
    {
        $event = $this->dispatch('GET', '/api/v1/profiles', [self::SESSION_COOKIE => 'sess']);

        $this->assertNull($event->getResponse());
    }

    public function testLoginEnforcedEvenWithoutSession(): void
    {
        // Login hat noch keine Session, CSRF ist trotzdem Pflicht → fehlendes Token = 403.
        $event = $this->dispatch('POST', '/api/v1/auth/login');

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testLoginPassesWithValidToken(): void
    {
        $event = $this->dispatch(
            'POST',
            '/api/v1/auth/login',
            [self::CSRF_COOKIE => 'logintok'],
            [self::CSRF_HEADER => 'logintok'],
        );

        $this->assertNull($event->getResponse());
    }

    public function testCsrfEndpointItselfSkipped(): void
    {
        // /auth/csrf ist GET → ohnehin safe; zusätzlich in der Whitelist.
        $event = $this->dispatch('GET', '/api/v1/auth/csrf');

        $this->assertNull($event->getResponse());
    }

    public function testNonApiPathSkipped(): void
    {
        $event = $this->dispatch('POST', '/health', [self::SESSION_COOKIE => 'sess']);

        $this->assertNull($event->getResponse());
    }
}

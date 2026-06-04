<?php

declare(strict_types=1);

namespace App\Module\Admin\Infrastructure\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Ersetzt den LogoutSuccessHandlerInterface (seit Symfony 6 entfernt).
 * Sendet 204 No Content nach Logout – kein Redirect, kein Body.
 */
final class AuthLogoutSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => 'onLogout'];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $event->setResponse(new Response(null, Response::HTTP_NO_CONTENT));
    }
}

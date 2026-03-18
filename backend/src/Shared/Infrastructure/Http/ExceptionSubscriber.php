<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Module\SetupWizard\Domain\Exception\StepValidationException;
use App\Module\Spotify\Domain\Exception\SpotifyNoDeviceException;
use App\Module\Spotify\Domain\Exception\SpotifyNotConnectedException;
use App\Module\Spotify\Domain\Exception\SpotifyOAuthStateException;
use App\Module\Spotify\Domain\Exception\SpotifyScopeMissingException;
use App\Module\Spotify\Domain\Exception\SpotifyTokenInvalidException;
use App\Shared\Application\Exception\HttpException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Maps both HttpException (Application layer) and pure Domain exceptions to Problem+JSON responses.
 * This is the single place where HTTP status codes are assigned to domain errors.
 */
final class ExceptionSubscriber implements EventSubscriberInterface
{
    /** Maps domain exception FQCN → HTTP status code. */
    private const DOMAIN_EXCEPTION_MAP = [
        SpotifyNotConnectedException::class => Response::HTTP_NOT_FOUND,
        SpotifyTokenInvalidException::class  => Response::HTTP_UNAUTHORIZED,
        SpotifyNoDeviceException::class      => Response::HTTP_UNPROCESSABLE_ENTITY,
        SpotifyScopeMissingException::class  => Response::HTTP_FORBIDDEN,
        SpotifyOAuthStateException::class    => Response::HTTP_BAD_REQUEST,
        StepValidationException::class       => Response::HTTP_UNPROCESSABLE_ENTITY,
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        $instance = $event->getRequest()->getRequestUri();

        if ($e instanceof HttpException) {
            $response = ProblemJsonResponse::fromThrowable($e, $instance);
            $event->setResponse($response);
            return;
        }

        foreach (self::DOMAIN_EXCEPTION_MAP as $exceptionClass => $statusCode) {
            if ($e instanceof $exceptionClass) {
                $response = ProblemJsonResponse::fromDomainException($e, $statusCode, $instance);
                $event->setResponse($response);
                return;
            }
        }
    }
}

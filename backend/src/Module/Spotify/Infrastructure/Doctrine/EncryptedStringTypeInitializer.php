<?php

declare(strict_types=1);

namespace App\Module\Spotify\Infrastructure\Doctrine;

use App\Module\Spotify\Application\Port\TokenEncryptionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the encryptor on the custom type so it can encrypt/decrypt (type is not a service).
 */
final class EncryptedStringTypeInitializer implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenEncryptionInterface $encryptor,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        EncryptedStringType::setEncryptor($this->encryptor);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255],
        ];
    }
}

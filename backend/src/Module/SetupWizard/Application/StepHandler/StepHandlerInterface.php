<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Application\StepHandler;

/**
 * Strategy interface for wizard step handlers.
 * Each handler is responsible for one or more step keys.
 */
interface StepHandlerInterface
{
    public function supports(string $stepKey): bool;

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(string $profileId, string $stepKey, array $payload): void;
}

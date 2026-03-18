<?php

declare(strict_types=1);

namespace App\Module\SetupWizard\Domain\Exception;

/**
 * A wizard step submission failed validation.
 * Pure domain exception — no HTTP details.
 */
final class StepValidationException extends \DomainException
{
    /** @param array<int, array{step_key: string, status: string, payload: array|null}> $steps */
    public function __construct(
        string $message,
        ?\Throwable $previous = null,
        public readonly string $stepKey = '',
        public readonly array $steps = [],
    ) {
        parent::__construct($message, 0, $previous);
    }
}

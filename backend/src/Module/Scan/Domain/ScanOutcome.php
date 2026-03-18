<?php

declare(strict_types=1);

namespace App\Module\Scan\Domain;

/**
 * Scan event outcome and error codes for reader scan flow.
 */
final class ScanOutcome
{
    public const SUCCESS = 'success';
    public const UNKNOWN_CARD = 'unknown_card';
    public const NO_BINDING = 'no_binding';
    public const NO_DEVICE = 'no_device';
    public const TOKEN_INVALID = 'token_invalid';
    public const PLAYBACK_FAILED = 'playback_failed';
    public const DEBOUNCED = 'debounced';
    public const INVALID_REQUEST = 'invalid_request';
    public const UNKNOWN_READER = 'unknown_reader';
}

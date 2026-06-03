<?php

declare(strict_types=1);

namespace App\Module\Scan\Application;

final class ReaderClaimCode
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LENGTH = 8;

    public static function generate(): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }
        return $out;
    }

    public static function normalize(string $claimCode): string
    {
        return strtoupper(trim(str_replace([' ', '-'], '', $claimCode)));
    }

    public static function isValid(string $claimCode): bool
    {
        return preg_match('/^[A-HJ-NP-Z2-9]{8}$/', self::normalize($claimCode)) === 1;
    }

    public static function hash(string $claimCode): string
    {
        return hash('sha256', self::normalize($claimCode));
    }
}

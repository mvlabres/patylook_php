<?php

declare(strict_types=1);

namespace Patylook\Support;

/** Unicode-aware string helpers (the native ones only understand ASCII whitespace and bytes). */
final class Str
{
    private function __construct()
    {
    }

    /** Trims Unicode whitespace from both ends. */
    public static function trim(string $s): string
    {
        return (string) preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $s);
    }

    public static function length(string $s): int
    {
        return mb_strlen($s, 'UTF-8');
    }

    public static function lower(string $s): string
    {
        return mb_strtolower($s, 'UTF-8');
    }

    public static function upper(string $s): string
    {
        return mb_strtoupper($s, 'UTF-8');
    }

    public static function onlyDigits(string $s): string
    {
        return (string) preg_replace('/[^0-9]/', '', $s);
    }

    /** Parses a decimal integer strictly (optional sign, digits only); null when it is not one. */
    public static function toInt(string $s): ?int
    {
        if (preg_match('/^[+-]?\d{1,18}$/', $s) !== 1) {
            return null;
        }
        return (int) $s;
    }
}

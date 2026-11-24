<?php

declare(strict_types=1);

namespace Patylook\Support;

/** Fixed-point arithmetic with two decimal places, shared by Money and Percent. */
final class Fixed2
{
    private function __construct()
    {
    }

    /** Parses "1234.56", "1234,56" or "1.234,56" into hundredths; null when invalid. */
    public static function parse(string $s): ?int
    {
        $s = Str::trim($s);
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $pos = (int) strpos($s, ',');
            $s = substr($s, 0, $pos) . '.' . substr($s, $pos + 1);
        }
        $neg = str_starts_with($s, '-');
        if ($neg) {
            $s = substr($s, 1);
        }

        $dot = strpos($s, '.');
        $intPart = $dot === false ? $s : substr($s, 0, $dot);
        $fracPart = $dot === false ? '' : substr($s, $dot + 1);
        if ($intPart === '' && $fracPart === '') {
            return null;
        }
        if (strlen($fracPart) > 2 || !self::onlyDigits($intPart) || !self::onlyDigits($fracPart)) {
            return null;
        }
        $fracPart .= str_repeat('0', 2 - strlen($fracPart));
        $intPart = ltrim($intPart, '0');
        if ($intPart === '') {
            $intPart = '0';
        }
        // Keeps value * 100 inside the 64-bit range.
        if (strlen($intPart) > 17) {
            return null;
        }
        $v = (int) $intPart * 100 + (int) $fracPart;
        return $neg ? -$v : $v;
    }

    private static function onlyDigits(string $s): bool
    {
        return $s === '' || ctype_digit($s);
    }

    public static function format(int $v, string $sep): string
    {
        $sign = '';
        if ($v < 0) {
            $sign = '-';
            $v = -$v;
        }
        return sprintf('%s%d%s%02d', $sign, intdiv($v, 100), $sep, $v % 100);
    }

    public static function groupThousands(int $n): string
    {
        $s = (string) $n;
        for ($i = strlen($s) - 3; $i > 0; $i -= 3) {
            $s = substr($s, 0, $i) . '.' . substr($s, $i);
        }
        return $s;
    }

    /** Integer division rounding half away from zero. */
    public static function roundDiv(int $n, int $d): int
    {
        if ($n < 0) {
            return -self::roundDiv(-$n, $d);
        }
        return intdiv($n + intdiv($d, 2), $d);
    }
}

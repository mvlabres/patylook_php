<?php

declare(strict_types=1);

namespace Patylook\Support;

/** Helpers for wa.me links. */
final class Whatsapp
{
    /** Brazil's country code, added when a number has only area code and number. */
    private const COUNTRY_CODE = '55';

    private function __construct()
    {
    }

    /**
     * The number in the international form wa.me needs: digits only, with country code. A Brazilian
     * number written without it (area code + 8 or 9 digits) gets 55. Null when nothing usable is given.
     */
    public static function digits(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        return strlen($digits) <= 11 ? self::COUNTRY_CODE . $digits : $digits;
    }

    /** Link that opens a chat with the number, optionally with the text already filled in. */
    public static function chatUrl(string $digits, string $text = ''): string
    {
        return 'https://wa.me/' . $digits . ($text === '' ? '' : '?text=' . rawurlencode($text));
    }
}

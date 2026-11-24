<?php

declare(strict_types=1);

/**
 * Template helpers. Loaded once by the bootstrap; kept to the single escaping function the
 * templates use everywhere.
 */

if (!function_exists('e')) {
    /** Escapes a value for safe output in HTML text and attributes. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

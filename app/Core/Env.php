<?php

declare(strict_types=1);

namespace Patylook\Core;

/**
 * Reads KEY=VALUE settings. A real environment variable always wins over the .env file, so on the
 * server the configuration can live in the hosting panel instead of a file.
 */
final class Env
{
    private function __construct()
    {
    }

    /** Loads a .env file without overriding variables that are already set. A missing file is fine. */
    public static function load(string $file): void
    {
        $lines = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : false;
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            if ($key === '' || self::exists($key)) {
                continue;
            }
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    /** The variable's value, or $default when it is unset or empty. */
    public static function get(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? false; // Apache's SetEnv lands in $_SERVER
        }
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function exists(string $key): bool
    {
        return getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key]);
    }
}

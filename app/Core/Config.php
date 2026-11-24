<?php

declare(strict_types=1);

namespace Patylook\Core;

use DateTimeZone;
use Exception;
use Patylook\Exceptions\ConfigurationException;

/** Runtime settings, read from the environment (optionally seeded from a .env file). */
final readonly class Config
{
    public function __construct(
        public string $basePath,
        public string $env, // "development" | "production"
        public string $dbHost,
        public int $dbPort,
        public string $dbUser,
        public string $dbPassword,
        public string $dbName,
        public string $dbTls,
        public DateTimeZone $timezone,
        /** Idle session lifetime, in seconds. */
        public int $sessionTtl,
        public bool $cookieSecure,
        public string $adminCategory,
        /** Absolute directory with one folder per product (images/products/<SKU>). */
        public string $imagesDir,
        /** Absolute directory for sessions, login throttling, logs and caches. Must be writable. */
        public string $storageDir,
        // Optional first-run bootstrap, used only when the user table is empty.
        public string $bootstrapUsername,
        public string $bootstrapName,
        public string $bootstrapPassword,
    ) {
    }

    public function isProduction(): bool
    {
        return $this->env === 'production';
    }

    public function storagePath(string $sub = ''): string
    {
        return $this->storageDir . ($sub === '' ? '' : DIRECTORY_SEPARATOR . $sub);
    }

    /**
     * Reads the configuration, applying the .env file (if present) without overriding real
     * environment variables.
     *
     * @throws ConfigurationException listing every invalid setting
     */
    public static function load(string $basePath, ?string $envFile = null): self
    {
        Env::load($envFile ?? $basePath . DIRECTORY_SEPARATOR . '.env');
        $errors = [];

        $port = 3306;
        $rawPort = Env::get('DB_PORT');
        if ($rawPort !== '') {
            if (preg_match('/^\d{1,5}$/D', $rawPort) === 1) {
                $port = (int) $rawPort;
            } else {
                $errors[] = sprintf('DB_PORT inválido: "%s"', $rawPort);
            }
        }

        $tzName = Env::get('APP_TIMEZONE', 'America/Sao_Paulo');
        try {
            $timezone = new DateTimeZone($tzName);
        } catch (Exception) {
            $errors[] = sprintf('APP_TIMEZONE inválido "%s"', $tzName);
            $timezone = new DateTimeZone('UTC');
        }

        $ttl = self::parseDuration(Env::get('SESSION_TTL', '8h'));
        if ($ttl === null || $ttl <= 0) {
            $errors[] = 'SESSION_TTL inválido (ex.: 8h, 30m)';
            $ttl = 8 * 3600;
        }

        $env = strtolower(Env::get('APP_ENV', 'development'));
        $rawSecure = Env::get('COOKIE_SECURE');
        $secure = $env === 'production';
        if ($rawSecure !== '') {
            $parsed = filter_var($rawSecure, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed === null) {
                $errors[] = sprintf('COOKIE_SECURE inválido: "%s"', $rawSecure);
            } else {
                $secure = $parsed;
            }
        }

        $user = Env::get('DB_USER');
        if ($user === '') {
            $errors[] = 'DB_USER é obrigatório';
        }
        if ($errors !== []) {
            throw new ConfigurationException(implode('; ', $errors));
        }

        return new self(
            basePath: $basePath,
            env: $env,
            dbHost: Env::get('DB_HOST', '127.0.0.1'),
            dbPort: $port,
            dbUser: $user,
            dbPassword: Env::get('DB_PASSWORD'),
            dbName: Env::get('DB_NAME', 'patylook'),
            dbTls: Env::get('DB_TLS'),
            timezone: $timezone,
            sessionTtl: $ttl,
            cookieSecure: $secure,
            adminCategory: Env::get('ADMIN_CATEGORY', 'admin'),
            imagesDir: self::absolute($basePath, Env::get('IMAGES_DIR', 'images/products')),
            storageDir: self::absolute($basePath, Env::get('STORAGE_DIR', 'storage')),
            bootstrapUsername: Env::get('BOOTSTRAP_ADMIN_USERNAME'),
            bootstrapName: Env::get('BOOTSTRAP_ADMIN_NAME', 'Administrador'),
            bootstrapPassword: Env::get('BOOTSTRAP_ADMIN_PASSWORD'),
        );
    }

    /** Relative paths are resolved from the project root, not from the (variable) working directory. */
    private static function absolute(string $basePath, string $path): string
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }
        return $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Parses durations such as "8h", "30m", "90s" or "1h30m" into seconds. Null when invalid.
     */
    public static function parseDuration(string $text): ?int
    {
        $text = trim($text);
        if ($text === '0') {
            return 0;
        }
        if ($text === '' || preg_match('/^(?:\d+(?:\.\d+)?(?:ms|s|m|h))+$/D', $text) !== 1) {
            return null;
        }
        preg_match_all('/(\d+(?:\.\d+)?)(ms|s|m|h)/', $text, $parts, PREG_SET_ORDER);
        $seconds = 0.0;
        foreach ($parts as [, $amount, $unit]) {
            $seconds += (float) $amount * match ($unit) {
                'h' => 3600,
                'm' => 60,
                's' => 1,
                'ms' => 0.001,
            };
        }
        return (int) round($seconds);
    }
}

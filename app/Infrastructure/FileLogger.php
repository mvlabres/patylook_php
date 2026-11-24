<?php

declare(strict_types=1);

namespace Patylook\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Patylook\Contracts\LoggerInterface;
use Throwable;

/** Appends one line per event to a log file; plain text in development, JSON in production. */
final class FileLogger implements LoggerInterface
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    public function __construct(
        private readonly string $file,
        private readonly bool $json,
        private readonly DateTimeZone $timezone,
        private readonly string $minimumLevel = 'info',
    ) {
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function log(string $level, string $message, array $context): void
    {
        if (self::LEVELS[$level] < self::LEVELS[$this->minimumLevel]) {
            return;
        }
        $time = (new DateTimeImmutable('now', $this->timezone))->format('c');
        $context = array_map(fn (mixed $v): mixed => $v instanceof Throwable ? $v->getMessage() : $v, $context);

        if ($this->json) {
            $line = json_encode(['time' => $time, 'level' => $level, 'msg' => $message] + $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        } else {
            $parts = [];
            foreach ($context as $key => $value) {
                $parts[] = $key . '=' . (is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
            }
            $line = sprintf('%s %-7s %s %s', $time, strtoupper($level), $message, implode(' ', $parts));
        }

        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0o755, true);
        }
        if (@file_put_contents($this->file, rtrim((string) $line) . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log('patylook: ' . $level . ' ' . $message);
        }
    }
}

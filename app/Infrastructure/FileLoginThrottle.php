<?php

declare(strict_types=1);

namespace Patylook\Infrastructure;

use DateTimeImmutable;
use Patylook\Contracts\LoginThrottleInterface;
use RuntimeException;

/**
 * Throttles repeated failed logins per key (client + username). State lives in small files, one per
 * key, because PHP does not keep memory between requests. After $max failures inside $window
 * seconds the key is blocked until the oldest failure leaves the window.
 */
final class FileLoginThrottle implements LoginThrottleInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly int $max = 5,
        private readonly int $window = 900,
    ) {
    }

    public function blocked(string $key, DateTimeImmutable $now): bool
    {
        $file = $this->fileFor($key);
        if (!is_file($file)) {
            return false;
        }
        return count($this->recent($this->read($file), $now)) >= $this->max;
    }

    public function fail(string $key, DateTimeImmutable $now): void
    {
        $this->ensureDirectory();
        $file = $this->fileFor($key);
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new RuntimeException('não foi possível gravar o controle de tentativas de login');
        }
        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $times = $this->recent($this->decode($raw === false ? '' : $raw), $now);
            $times[] = $now->getTimestamp();
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($times, JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
        $this->sweepSometimes($now);
    }

    public function reset(string $key): void
    {
        $file = $this->fileFor($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function fileFor(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('não foi possível criar a pasta ' . $this->directory);
        }
    }

    /** @return list<int> */
    private function read(string $file): array
    {
        $raw = @file_get_contents($file);
        return $this->decode($raw === false ? '' : $raw);
    }

    /** @return list<int> */
    private function decode(string $raw): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }
        return array_values(array_map('intval', $data));
    }

    /**
     * @param list<int> $times
     * @return list<int>
     */
    private function recent(array $times, DateTimeImmutable $now): array
    {
        $cutoff = $now->getTimestamp() - $this->window;
        return array_values(array_filter($times, fn (int $t): bool => $t >= $cutoff));
    }

    /** Bounds the disk used under attack by dropping files whose failures have all expired. */
    private function sweepSometimes(DateTimeImmutable $now): void
    {
        if (random_int(1, 50) !== 1) {
            return;
        }
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            if ($this->recent($this->read($file), $now) === []) {
                @unlink($file);
            }
        }
    }
}

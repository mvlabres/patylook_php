<?php

declare(strict_types=1);

namespace Patylook\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Patylook\Contracts\SessionStoreInterface;
use Patylook\Exceptions\NotFoundException;
use Patylook\Models\Flash;
use Patylook\Models\FlashKind;
use Patylook\Models\Session;
use RuntimeException;

/**
 * Keeps sessions as small JSON files, one per session, named after the hash of the cookie token.
 * PHP serves each request in a fresh process, so unlike an in-memory map this survives between
 * requests without needing an extra database table (the existing schema is never altered).
 */
final class FileSessionStore implements SessionStoreInterface
{
    public function __construct(private readonly string $directory, private readonly DateTimeZone $timezone)
    {
    }

    public function create(Session $session): void
    {
        $this->write($session);
    }

    public function find(string $tokenHash): Session
    {
        $file = $this->fileFor($tokenHash);
        $raw = is_file($file) ? @file_get_contents($file) : false;
        if ($raw === false) {
            throw new NotFoundException();
        }
        $session = $this->decode($raw, $tokenHash);
        if ($session === null) {
            throw new NotFoundException();
        }
        return $session;
    }

    public function update(Session $session): void
    {
        if (!is_file($this->fileFor($session->tokenHash))) {
            throw new NotFoundException();
        }
        $this->write($session);
    }

    public function delete(string $tokenHash): void
    {
        $file = $this->fileFor($tokenHash);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function deleteByUser(int $userId): void
    {
        foreach ($this->files() as $file) {
            $data = $this->readData($file);
            if ($data !== null && ($data['user_id'] ?? null) === $userId) {
                @unlink($file);
            }
        }
    }

    public function deleteExpired(DateTimeImmutable $now): int
    {
        $removed = 0;
        foreach ($this->files() as $file) {
            $data = $this->readData($file);
            // Unreadable files are removed too: they can never be used again.
            if ($data === null || (int) ($data['expires'] ?? 0) <= $now->getTimestamp()) {
                if (@unlink($file)) {
                    $removed++;
                }
            }
        }
        return $removed;
    }

    /** @return list<string> */
    private function files(): array
    {
        return glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
    }

    private function fileFor(string $tokenHash): string
    {
        // Only hex digests are ever generated, so anything else cannot name a session file.
        if (preg_match('/^[a-f0-9]{64}$/D', $tokenHash) !== 1) {
            throw new NotFoundException();
        }
        return $this->directory . DIRECTORY_SEPARATOR . $tokenHash . '.json';
    }

    private function write(Session $session): void
    {
        $file = $this->fileFor($session->tokenHash);
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('não foi possível criar a pasta de sessões: ' . $this->directory);
        }
        $payload = json_encode([
            'user_id' => $session->userId,
            'csrf' => $session->csrfToken,
            'created' => $session->createdAt->getTimestamp(),
            'expires' => $session->expiresAt->getTimestamp(),
            'flash' => $session->flash === null ? null : [
                'kind' => $session->flash->kind->value,
                'message' => $session->flash->message,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        // Write to a temporary file and rename, so readers never see a partial session.
        $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            throw new RuntimeException('não foi possível gravar a sessão em ' . $this->directory);
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('não foi possível gravar a sessão em ' . $this->directory);
        }
    }

    /** @return array<string, mixed>|null */
    private function readData(string $file): ?array
    {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        return is_array($data) ? $data : null;
    }

    private function decode(string $raw, string $tokenHash): ?Session
    {
        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($data) || !isset($data['user_id'], $data['csrf'], $data['created'], $data['expires'])) {
            return null;
        }
        $flash = null;
        if (isset($data['flash']['kind'], $data['flash']['message'])) {
            $kind = FlashKind::tryFrom((string) $data['flash']['kind']);
            if ($kind !== null) {
                $flash = new Flash($kind, (string) $data['flash']['message']);
            }
        }
        return new Session(
            $tokenHash,
            (int) $data['user_id'],
            (string) $data['csrf'],
            $this->fromTimestamp((int) $data['created']),
            $this->fromTimestamp((int) $data['expires']),
            $flash,
        );
    }

    private function fromTimestamp(int $ts): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . $ts))->setTimezone($this->timezone);
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Services;

use DateInterval;
use Patylook\Contracts\AuthServiceInterface;
use Patylook\Contracts\ClockInterface;
use Patylook\Contracts\LoginThrottleInterface;
use Patylook\Contracts\PasswordHasherInterface;
use Patylook\Contracts\SessionStoreInterface;
use Patylook\Contracts\UserRepositoryInterface;
use Patylook\Dto\LoginResult;
use Patylook\Dto\Principal;
use Patylook\Exceptions\InvalidCredentialsException;
use Patylook\Exceptions\NotFoundException;
use Patylook\Exceptions\TooManyAttemptsException;
use Patylook\Exceptions\UnauthenticatedException;
use Patylook\Models\Flash;
use Patylook\Models\FlashKind;
use Patylook\Models\Session;
use Patylook\Services\Support\AdminRule;
use Patylook\Support\Str;

final class AuthService implements AuthServiceInterface
{
    /** bcrypt (cost 12) hash of a throwaway password, verified when the username does not exist. */
    private const DUMMY_HASH = '$2y$12$fRTvatxLO8kzAA89S6R8qOqxL7FE.1hIKkrad9PuOE30AWOmrn5a2';

    /**
     * @param int $sessionTtl idle session lifetime, in seconds
     */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly SessionStoreInterface $sessions,
        private readonly PasswordHasherInterface $hasher,
        private readonly LoginThrottleInterface $throttle,
        private readonly AdminRule $admin,
        private readonly ClockInterface $clock,
        private readonly int $sessionTtl,
    ) {
    }

    public function login(string $clientKey, string $username, string $password): LoginResult
    {
        $username = Str::trim($username);
        $key = $clientKey . '|' . Str::lower($username);
        $now = $this->clock->now();
        if ($this->throttle->blocked($key, $now)) {
            throw new TooManyAttemptsException();
        }

        try {
            $user = $this->users->findByUsername($username);
        } catch (NotFoundException) {
            // Spend the time a real check would, so the response does not reveal valid usernames.
            $this->hasher->verify(self::DUMMY_HASH, $password);
            $this->throttle->fail($key, $now);
            throw new InvalidCredentialsException();
        }
        if (!$this->hasher->verify($user->passwordHash, $password) || !$user->isActive) {
            $this->throttle->fail($key, $now);
            throw new InvalidCredentialsException();
        }
        $this->throttle->reset($key);

        $token = self::randomToken();
        $session = new Session(
            self::hashToken($token),
            $user->id,
            self::randomToken(),
            $now,
            $now->add(new DateInterval('PT' . $this->sessionTtl . 'S')),
        );
        $this->sessions->create($session);
        return new LoginResult($token, $session, $this->admin->mark($user));
    }

    public function authenticate(string $token): Principal
    {
        if ($token === '') {
            throw new UnauthenticatedException();
        }
        $hash = self::hashToken($token);
        try {
            $session = $this->sessions->find($hash);
        } catch (NotFoundException) {
            throw new UnauthenticatedException();
        }
        $now = $this->clock->now();
        if ($session->expiresAt <= $now) {
            $this->sessions->delete($hash);
            throw new UnauthenticatedException();
        }
        try {
            $user = $this->users->findById($session->userId);
        } catch (NotFoundException) {
            $this->sessions->delete($hash);
            throw new UnauthenticatedException();
        }
        if (!$user->isActive) {
            $this->sessions->delete($hash);
            throw new UnauthenticatedException();
        }
        // Sliding expiration, refreshed only once half the TTL has elapsed to limit writes.
        if ($session->expiresAt->getTimestamp() - $now->getTimestamp() < $this->sessionTtl / 2) {
            $session->expiresAt = $now->add(new DateInterval('PT' . $this->sessionTtl . 'S'));
            $this->sessions->update($session);
        }
        return new Principal($this->admin->mark($user), $session, $token);
    }

    public function logout(string $token): void
    {
        if ($token !== '') {
            $this->sessions->delete(self::hashToken($token));
        }
    }

    public function setFlash(Session $session, FlashKind $kind, string $message): void
    {
        $session->flash = new Flash($kind, $message);
        $this->sessions->update($session);
    }

    public function popFlash(Session $session): ?Flash
    {
        $flash = $session->flash;
        if ($flash === null) {
            return null;
        }
        $session->flash = null;
        try {
            $this->sessions->update($session);
        } catch (NotFoundException) {
            // The session vanished meanwhile; the message is still shown once.
        }
        return $flash;
    }

    private static function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}

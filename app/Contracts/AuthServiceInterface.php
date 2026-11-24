<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\LoginResult;
use Patylook\Dto\Principal;
use Patylook\Models\FlashKind;
use Patylook\Models\Flash;
use Patylook\Models\Session;

interface AuthServiceInterface
{
    /**
     * Authenticates and opens a session. $clientKey identifies the caller for throttling.
     *
     * @throws \Patylook\Exceptions\InvalidCredentialsException
     * @throws \Patylook\Exceptions\TooManyAttemptsException
     */
    public function login(string $clientKey, string $username, string $password): LoginResult;

    /**
     * Resolves the raw cookie token into the active session and user.
     *
     * @throws \Patylook\Exceptions\UnauthenticatedException
     */
    public function authenticate(string $token): Principal;

    public function logout(string $token): void;

    public function setFlash(Session $session, FlashKind $kind, string $message): void;

    /** Returns and clears the pending flash message. */
    public function popFlash(Session $session): ?Flash;
}

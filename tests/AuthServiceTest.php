<?php

declare(strict_types=1);

namespace Patylook\Tests;

use Patylook\Exceptions\ForbiddenException;
use Patylook\Exceptions\InvalidCredentialsException;
use Patylook\Exceptions\NotFoundException;
use Patylook\Exceptions\TooManyAttemptsException;
use Patylook\Exceptions\UnauthenticatedException;
use Patylook\Exceptions\ValidationException;
use Patylook\Infrastructure\FileLoginThrottle;
use Patylook\Infrastructure\FileSessionStore;
use Patylook\Models\FlashKind;
use Patylook\Models\Session;
use Patylook\Models\User;
use Patylook\Services\AuthService;
use Patylook\Services\Support\AdminRule;
use Patylook\Services\UserService;
use Patylook\Tests\Fakes\FakeClock;
use Patylook\Tests\Fakes\FakeHasher;
use Patylook\Tests\Fakes\FakeSessions;
use Patylook\Tests\Fakes\FakeUsers;

/** Authentication, session lifecycle, throttling and user administration. */
final class AuthServiceTest extends TestCase
{
    private string $dir;
    private FakeClock $clock;
    private FileSessionStore $sessions;
    private FakeUsers $users;
    private AuthService $auth;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pl_auth_' . bin2hex(random_bytes(4));
        $this->clock = new FakeClock();
        $this->sessions = new FileSessionStore($this->dir . '/sessions', $this->clock->now()->getTimezone());
        $this->users = new FakeUsers(
            new User(id: 1, username: 'ana', passwordHash: 'hash:secret123', isActive: true, categoryName: 'admin', categoryId: 1),
            new User(id: 2, username: 'off', passwordHash: 'hash:secret123', isActive: false),
        );
        $this->auth = $this->makeAuth($this->sessions);
    }

    private function makeAuth(\Patylook\Contracts\SessionStoreInterface $sessions): AuthService
    {
        return new AuthService(
            $this->users,
            $sessions,
            new FakeHasher(),
            new FileLoginThrottle($this->dir . '/throttle', 5, 900),
            new AdminRule('admin'),
            $this->clock,
            3600,
        );
    }

    protected function tearDown(): void
    {
        foreach (['sessions', 'throttle'] as $sub) {
            foreach (glob($this->dir . '/' . $sub . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->dir . '/' . $sub);
        }
        @rmdir($this->dir);
    }

    public function testLoginAndAuthenticate(): void
    {
        $res = $this->auth->login('1.2.3.4', 'ana', 'secret123');
        $this->assertTrue($res->token !== '' && $res->session->csrfToken !== '' && $res->user->admin);
        $this->assertTrue($res->session->tokenHash !== $res->token, 'o armazenamento guarda o hash, não o token');
        $this->assertSame(hash('sha256', $res->token), $res->session->tokenHash);

        $principal = $this->auth->authenticate($res->token);
        $this->assertSame('ana', $principal->user->username);
        $this->assertSame($res->session->csrfToken, $principal->session->csrfToken);

        $this->assertThrows(UnauthenticatedException::class, fn () => $this->auth->authenticate('forged'));
        $this->assertThrows(UnauthenticatedException::class, fn () => $this->auth->authenticate(''));
    }

    public function testLoginFailures(): void
    {
        foreach ([['ana', 'wrong'], ['nobody', 'secret123'], ['off', 'secret123']] as [$user, $pass]) {
            $this->assertThrows(InvalidCredentialsException::class, fn () => $this->auth->login('ip', $user, $pass), $user);
        }
    }

    public function testLoginThrottling(): void
    {
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->auth->login('ip', 'ana', 'wrong');
            } catch (InvalidCredentialsException) {
            }
        }
        $this->assertThrows(TooManyAttemptsException::class, fn () => $this->auth->login('ip', 'ana', 'secret123'), 'bloqueado mesmo com a senha certa');
        $this->auth->login('other-ip', 'ana', 'secret123'); // outro cliente não é afetado
        $this->clock->advance('+901 seconds');
        $this->auth->login('ip', 'ana', 'secret123'); // o bloqueio expira com a janela
        $this->assertTrue(true);
    }

    public function testSessionExpiryAndDeactivation(): void
    {
        $res = $this->auth->login('ip', 'ana', 'secret123');
        $this->users->byId[1]->isActive = false;
        $this->assertThrows(UnauthenticatedException::class, fn () => $this->auth->authenticate($res->token), 'usuário desativado');

        $this->users->byId[1]->isActive = true;
        $res = $this->auth->login('ip', 'ana', 'secret123');
        $this->clock->advance('+2 hours');
        $this->assertThrows(UnauthenticatedException::class, fn () => $this->auth->authenticate($res->token), 'sessão expirada');
        $this->assertThrows(NotFoundException::class, fn () => $this->sessions->find($res->session->tokenHash), 'sessão expirada é apagada');
    }

    public function testSlidingExpiration(): void
    {
        $res = $this->auth->login('ip', 'ana', 'secret123');
        $this->clock->advance('+40 minutes'); // past half the TTL
        $this->auth->authenticate($res->token);
        $stored = $this->sessions->find($res->session->tokenHash);
        $this->assertSame($this->clock->now()->modify('+1 hour')->getTimestamp(), $stored->expiresAt->getTimestamp());
    }

    public function testLogoutAndFlash(): void
    {
        $res = $this->auth->login('ip', 'ana', 'secret123');
        $this->auth->setFlash($this->auth->authenticate($res->token)->session, FlashKind::Success, 'ok');
        $flash = $this->auth->popFlash($this->auth->authenticate($res->token)->session);
        $this->assertSame('ok', $flash?->message);
        $this->assertSame(FlashKind::Success, $flash?->kind);
        $this->assertSame(null, $this->auth->popFlash($this->auth->authenticate($res->token)->session), 'mensagem consumida');

        $this->auth->logout($res->token);
        $this->assertThrows(UnauthenticatedException::class, fn () => $this->auth->authenticate($res->token));
    }

    public function testSessionStoreRejectsUnsafeNamesAndExpires(): void
    {
        $this->assertThrows(NotFoundException::class, fn () => $this->sessions->find('../../etc/passwd'));
        $now = $this->clock->now();
        $this->sessions->create(new Session(str_repeat('a', 64), 1, 'c', $now, $now->modify('-1 minute')));
        $this->sessions->create(new Session(str_repeat('b', 64), 2, 'c', $now, $now->modify('+1 hour')));
        $this->assertSame(1, $this->sessions->deleteExpired($now));
        $this->sessions->deleteByUser(2);
        $this->assertThrows(NotFoundException::class, fn () => $this->sessions->find(str_repeat('b', 64)));
    }

    public function testUserServicePermissions(): void
    {
        $admin = new User(id: 1, admin: true);
        $operator = new User(id: 2);
        $svc = new UserService(new FakeUsers(new User(id: 1, isActive: true), new User(id: 2, isActive: true)), new FakeSessions(), new FakeHasher(), new AdminRule('admin'));

        $this->assertThrows(ForbiddenException::class, fn () => $svc->list($operator, 1, ''));
        $this->assertThrows(ForbiddenException::class, fn () => $svc->setActive($operator, 1, false));
        $this->assertThrows(ForbiddenException::class, fn () => $svc->resetPassword($operator, 1, 'newpassword1'));
        $this->assertThrows(ValidationException::class, fn () => $svc->setActive($admin, 1, false), 'admin não desativa a si mesmo');
    }

    public function testChangeOwnPasswordRevokesSessions(): void
    {
        $users = new FakeUsers(new User(id: 1, username: 'ana', passwordHash: 'hash:oldpassword', isActive: true));
        $sessions = new FakeSessions();
        $svc = new UserService($users, $sessions, new FakeHasher(), new AdminRule('admin'));
        $actor = new User(id: 1);

        $this->assertThrows(ValidationException::class, fn () => $svc->changeOwnPassword($actor, 'wrong', 'newpassword1'));
        $this->assertThrows(ValidationException::class, fn () => $svc->changeOwnPassword($actor, 'oldpassword', 'short'));
        $now = $this->clock->now();
        $sessions->create(new Session('t', 1, 'c', $now, $now->modify('+1 hour')));
        $svc->changeOwnPassword($actor, 'oldpassword', 'newpassword1');
        $this->assertSame('hash:newpassword1', $users->byId[1]->passwordHash);
        $this->assertSame([], $sessions->items, 'sessões revogadas');
    }

    public function testUpdateKeepsOwnCategoryAndBootstrapRunsOnlyOnEmptyTable(): void
    {
        $users = new FakeUsers(new User(id: 1, username: 'ana', categoryId: 1, isActive: true));
        $svc = new UserService($users, new FakeSessions(), new FakeHasher(), new AdminRule('admin'));
        $admin = new User(id: 1, admin: true);
        $svc->update($admin, new User(id: 1, username: 'ana', name: 'Ana', categoryId: 7));
        $this->assertSame(1, $users->byId[1]->categoryId, 'admin não troca a própria categoria');
        $this->assertFalse($svc->bootstrap('root', 'Root', 'longpassword1'), 'tabela não está vazia');

        $empty = new FakeUsers();
        $svc2 = new UserService($empty, new FakeSessions(), new FakeHasher(), new AdminRule('admin'));
        $this->assertTrue($svc2->bootstrap('root', 'Root', 'longpassword1'));
        $this->assertSame('hash:longpassword1', $empty->byId[1]->passwordHash);
        $this->assertThrows(ValidationException::class, fn () => (new UserService(new FakeUsers(), new FakeSessions(), new FakeHasher(), new AdminRule('admin')))->bootstrap('root', 'Root', 'short'));
    }
}

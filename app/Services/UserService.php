<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\AdminBootstrapInterface;
use Patylook\Contracts\PasswordHasherInterface;
use Patylook\Contracts\SessionStoreInterface;
use Patylook\Contracts\UserRepositoryInterface;
use Patylook\Contracts\UserServiceInterface;
use Patylook\Dto\Page;
use Patylook\Exceptions\ForbiddenException;
use Patylook\Exceptions\ValidationException;
use Patylook\Models\User;
use Patylook\Services\Support\AdminRule;
use Patylook\Services\Support\ConflictTranslator;
use Patylook\Services\Support\Pagination;

final class UserService implements UserServiceInterface, AdminBootstrapInterface
{
    private const CONFLICTS = [
        'username' => 'Já existe um usuário com esse login',
        'email' => 'Já existe um usuário com esse e-mail',
    ];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly SessionStoreInterface $sessions,
        private readonly PasswordHasherInterface $hasher,
        private readonly AdminRule $admin,
    ) {
    }

    public function list(User $actor, int $page, string $search): Page
    {
        $this->requireAdmin($actor);
        [$page, $params] = Pagination::params($page, $search);
        $result = $this->users->list($params);
        return Pagination::page($result->items, $result->total, $page);
    }

    public function get(User $actor, int $id): User
    {
        $this->requireAdmin($actor);
        return $this->admin->mark($this->users->findById($id));
    }

    public function categories(): array
    {
        return $this->users->listCategories();
    }

    public function create(User $actor, User $user, string $password): void
    {
        $this->requireAdmin($actor);
        $this->createUser($user, $password);
    }

    private function createUser(User $user, string $password): void
    {
        $user->normalize();
        $v = new ValidationException();
        try {
            $user->validate();
        } catch (ValidationException $e) {
            $v->merge($e);
        }
        try {
            User::validatePassword('password', $password);
        } catch (ValidationException $e) {
            $v->merge($e);
        }
        $v->throwIfAny();

        $user->passwordHash = $this->hasher->hash($password);
        $user->isActive = true;
        ConflictTranslator::run(fn () => $this->users->create($user), self::CONFLICTS);
    }

    public function bootstrap(string $username, string $name, string $password): bool
    {
        if ($this->users->count() > 0) {
            return false;
        }
        $categoryId = $this->users->ensureCategory($this->admin->adminCategory);
        $this->createUser(new User(username: $username, name: $name, categoryId: $categoryId), $password);
        return true;
    }

    public function update(User $actor, User $user): void
    {
        $this->requireAdmin($actor);
        $user->normalize();
        $user->validate();
        $current = $this->users->findById($user->id);
        if ($user->id === $actor->id) { // prevents an admin from locking themselves out
            $user->categoryId = $current->categoryId;
        }
        ConflictTranslator::run(fn () => $this->users->update($user), self::CONFLICTS);
    }

    public function resetPassword(User $actor, int $id, string $password): void
    {
        $this->requireAdmin($actor);
        User::validatePassword('password', $password);
        $this->users->findById($id);
        $this->users->updatePassword($id, $this->hasher->hash($password));
        $this->sessions->deleteByUser($id);
    }

    public function setActive(User $actor, int $id, bool $active): void
    {
        $this->requireAdmin($actor);
        if ($id === $actor->id && !$active) {
            throw ValidationException::single('', 'Você não pode desativar o próprio usuário');
        }
        $this->users->setActive($id, $active);
        if (!$active) {
            $this->sessions->deleteByUser($id);
        }
    }

    public function changeOwnPassword(User $actor, string $current, string $next): void
    {
        $user = $this->users->findById($actor->id);
        $v = new ValidationException();
        if (!$this->hasher->verify($user->passwordHash, $current)) {
            $v->add('current_password', 'Senha atual incorreta');
        }
        try {
            User::validatePassword('new_password', $next);
        } catch (ValidationException $e) {
            $v->merge($e);
        }
        $v->throwIfAny();

        $this->users->updatePassword($user->id, $this->hasher->hash($next));
        $this->sessions->deleteByUser($user->id); // forces re-login on every device
    }

    private function requireAdmin(User $actor): void
    {
        if (!$actor->admin) {
            throw new ForbiddenException();
        }
    }
}

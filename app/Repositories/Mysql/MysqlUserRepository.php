<?php

declare(strict_types=1);

namespace Patylook\Repositories\Mysql;

use Patylook\Contracts\UserRepositoryInterface;
use Patylook\Dto\ListParams;
use Patylook\Dto\ListResult;
use Patylook\Exceptions\NotFoundException;
use Patylook\Models\User;
use Patylook\Models\UserCategory;

final class MysqlUserRepository extends MysqlRepository implements UserRepositoryInterface
{
    private const SELECT = "SELECT u.id, u.username, COALESCE(u.email, '') AS email, u.name AS name, u.password, "
        . 'u.user_category_id, uc.name AS category_name, u.is_active, u.created_at '
        . 'FROM `user` u JOIN user_category uc ON uc.id = u.user_category_id ';

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            username: (string) $row['username'],
            email: (string) $row['email'],
            name: (string) $row['name'],
            passwordHash: (string) $row['password'],
            categoryId: (int) $row['user_category_id'],
            categoryName: (string) $row['category_name'],
            isActive: (bool) $row['is_active'],
            createdAt: $this->timestamp($row['created_at']),
        );
    }

    public function count(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM `user`');
    }

    public function findById(int $id): User
    {
        $row = $this->db->selectOne(self::SELECT . 'WHERE u.id = ?', [$id]);
        return $row === null ? throw new NotFoundException() : $this->hydrate($row);
    }

    public function findByUsername(string $username): User
    {
        $row = $this->db->selectOne(self::SELECT . 'WHERE u.username = ?', [$username]);
        return $row === null ? throw new NotFoundException() : $this->hydrate($row);
    }

    public function list(ListParams $params): ListResult
    {
        $where = '';
        $args = [];
        if ($params->search !== '') {
            $like = self::likePattern($params->search);
            $where = 'WHERE (u.username LIKE ? OR u.name LIKE ? OR u.email LIKE ?) ';
            $args = [$like, $like, $like];
        }
        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM `user` u ' . $where, $args);
        $rows = $this->db->select(
            self::SELECT . $where . 'ORDER BY u.name LIMIT ? OFFSET ?',
            [...$args, $params->limit, $params->offset],
        );
        return new ListResult(array_map($this->hydrate(...), $rows), $total);
    }

    public function create(User $user): void
    {
        $this->db->execute(
            'INSERT INTO `user` (username, email, password, user_category_id, name, is_active) VALUES (?, ?, ?, ?, ?, ?)',
            [$user->username, self::nullString($user->email), $user->passwordHash, $user->categoryId, $user->name, $user->isActive],
        );
        $user->id = $this->db->lastInsertId();
    }

    public function update(User $user): void
    {
        $this->db->execute(
            'UPDATE `user` SET username = ?, email = ?, name = ?, user_category_id = ? WHERE id = ?',
            [$user->username, self::nullString($user->email), $user->name, $user->categoryId, $user->id],
        );
    }

    public function updatePassword(int $id, string $hash): void
    {
        $this->db->execute('UPDATE `user` SET password = ? WHERE id = ?', [$hash, $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->execute('UPDATE `user` SET is_active = ? WHERE id = ?', [$active, $id]);
    }

    public function listCategories(): array
    {
        $rows = $this->db->select('SELECT id, name FROM user_category ORDER BY name');
        return array_map(fn (array $r): UserCategory => new UserCategory((int) $r['id'], (string) $r['name']), $rows);
    }

    public function ensureCategory(string $name): int
    {
        $id = $this->db->scalar('SELECT id FROM user_category WHERE name = ?', [$name]);
        if ($id !== null) {
            return (int) $id;
        }
        $this->db->execute('INSERT INTO user_category (name) VALUES (?)', [$name]);
        return $this->db->lastInsertId();
    }
}

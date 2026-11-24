<?php

declare(strict_types=1);

namespace Patylook\Models;

use DateTimeImmutable;
use Patylook\Exceptions\ValidationException;
use Patylook\Support\Str;

final class User
{
    public const MIN_PASSWORD_LENGTH = 8;

    /** bcrypt's input limit, in bytes. */
    public const MAX_PASSWORD_LENGTH = 72;

    public function __construct(
        public int $id = 0,
        public string $username = '',
        public string $email = '', // empty means NULL
        public string $name = '',
        public string $passwordHash = '',
        public int $categoryId = 0,
        public string $categoryName = '',
        public bool $isActive = false,
        public ?DateTimeImmutable $createdAt = null,
        /** Derived by the services from the configured admin category; not persisted. */
        public bool $admin = false,
    ) {
    }

    public function normalize(): void
    {
        $this->username = Str::trim($this->username);
        $this->email = Str::lower(Str::trim($this->email));
        $this->name = Str::trim($this->name);
    }

    /** @throws ValidationException */
    public function validate(): void
    {
        $v = new ValidationException();
        Rules::requireText($v, 'username', 'Usuário', $this->username, 16);
        if ($this->username !== '' && strlen($this->username) < 3) {
            $v->add('username', 'Usuário deve ter ao menos 3 caracteres');
        }
        if ($this->username !== '' && preg_match('/^[A-Za-z0-9_.-]+$/D', $this->username) !== 1) {
            $v->add('username', 'Usuário aceita apenas letras, números, ponto, hífen e sublinhado');
        }
        Rules::requireText($v, 'name', 'Nome', $this->name, 100);
        if ($this->email !== '') {
            Rules::optionalText($v, 'email', 'E-mail', $this->email, 255);
            if (!Rules::validEmail($this->email)) {
                $v->add('email', 'E-mail inválido');
            }
        }
        if ($this->categoryId <= 0) {
            $v->add('user_category_id', 'Categoria é obrigatória');
        }
        $v->throwIfAny();
    }

    /**
     * Checks the password policy.
     *
     * @throws ValidationException
     */
    public static function validatePassword(string $field, string $password): void
    {
        $v = new ValidationException();
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $v->add($field, 'Senha deve ter ao menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres');
        } elseif (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            $v->add($field, 'Senha deve ter no máximo ' . self::MAX_PASSWORD_LENGTH . ' caracteres');
        }
        $v->throwIfAny();
    }
}

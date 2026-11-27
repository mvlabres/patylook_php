<?php

declare(strict_types=1);

namespace Patylook\Models;

use DateTimeImmutable;
use Patylook\Exceptions\ValidationException;
use Patylook\Support\Str;

final class Customer
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public string $document = '', // CPF, digits only
        public string $phone = '',
        public string $email = '',
        public bool $isActive = false,
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }

    /** The first word of the name, capitalized ("MARIA SILVA" -> "Maria"); used to greet the customer. */
    public function firstName(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return $parts === [] ? '' : mb_convert_case($parts[0], MB_CASE_TITLE, 'UTF-8');
    }

    public function normalize(): void
    {
        $this->name = Str::trim($this->name);
        $this->document = Str::onlyDigits($this->document);
        $this->phone = Str::trim($this->phone);
        $this->email = Str::lower(Str::trim($this->email));
    }

    /** @throws ValidationException */
    public function validate(): void
    {
        $v = new ValidationException();
        Rules::requireText($v, 'name', 'Nome', $this->name, 100);
        if (!self::validCpf($this->document)) {
            $v->add('document', 'CPF inválido');
        }
        Rules::requireText($v, 'phone', 'Telefone', $this->phone, 20);
        Rules::requireText($v, 'email', 'E-mail', $this->email, 45);
        if ($this->email !== '' && !Rules::validEmail($this->email)) {
            $v->add('email', 'E-mail inválido');
        }
        $v->throwIfAny();
    }

    /** Validates the length and check digits of a CPF (digits only). */
    public static function validCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || !ctype_digit($cpf)) {
            return false;
        }
        if ($cpf === str_repeat($cpf[0], 11)) {
            return false;
        }
        return self::cpfDigit(substr($cpf, 0, 9)) === (int) $cpf[9]
            && self::cpfDigit(substr($cpf, 0, 10)) === (int) $cpf[10];
    }

    private static function cpfDigit(string $s): int
    {
        $sum = 0;
        $weight = strlen($s) + 1;
        for ($i = 0; $i < strlen($s); $i++, $weight--) {
            $sum += (int) $s[$i] * $weight;
        }
        $d = ($sum * 10) % 11;
        return $d === 10 ? 0 : $d;
    }
}

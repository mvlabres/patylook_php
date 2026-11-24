<?php

declare(strict_types=1);

namespace Patylook\Models;

use Patylook\Exceptions\ValidationException;
use Patylook\Support\Str;

/** Field validation helpers shared by the entities. */
final class Rules
{
    private function __construct()
    {
    }

    public static function requireText(ValidationException $v, string $field, string $label, string $value, int $max): void
    {
        if (Str::trim($value) === '') {
            $v->add($field, $label . ' é obrigatório');
        } elseif (Str::length($value) > $max) {
            $v->add($field, $label . ' deve ter no máximo ' . $max . ' caracteres');
        }
    }

    public static function optionalText(ValidationException $v, string $field, string $label, string $value, int $max): void
    {
        if (Str::length($value) > $max) {
            $v->add($field, $label . ' deve ter no máximo ' . $max . ' caracteres');
        }
    }

    /** True for a bare address ("user@example.com"); display names such as "Ana <a@b.c>" are refused. */
    public static function validEmail(string $s): bool
    {
        return filter_var($s, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false;
    }
}

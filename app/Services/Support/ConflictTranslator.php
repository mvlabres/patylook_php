<?php

declare(strict_types=1);

namespace Patylook\Services\Support;

use Patylook\Exceptions\ConflictException;
use Patylook\Exceptions\ValidationException;

/** Turns a repository unique-constraint violation into a field validation error. */
final class ConflictTranslator
{
    private function __construct()
    {
    }

    /**
     * Runs $work; a ConflictException becomes a ValidationException using the message registered for
     * the conflicting field (keyed by field name). Any other failure passes through untouched.
     *
     * @template T
     * @param callable(): T $work
     * @param array<string, string> $messages
     * @return T
     */
    public static function run(callable $work, array $messages): mixed
    {
        try {
            return $work();
        } catch (ConflictException $e) {
            throw self::toValidation($e, $messages);
        }
    }

    /** @param array<string, string> $messages */
    public static function toValidation(ConflictException $e, array $messages): ValidationException
    {
        return ValidationException::single(
            $e->field,
            $messages[$e->field] ?? 'Já existe um registro com esse valor',
        );
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Http;

use DateTimeImmutable;
use DateTimeZone;
use Patylook\Core\Request;
use Patylook\Exceptions\ValidationException;
use Patylook\Models\Money;
use Patylook\Models\Percent;
use Patylook\Support\Str;

/** Collects request values and the errors found while parsing them. */
final class Form
{
    public readonly ValidationException $errors;

    public function __construct(private readonly Request $request)
    {
        $this->errors = new ValidationException();
    }

    /** A trimmed form field. */
    public function str(string $key): string
    {
        return Str::trim($this->request->post($key));
    }

    /** An optional integer field; 0 when empty or invalid. */
    public function integer(string $key): int
    {
        return Str::toInt($this->str($key)) ?? 0;
    }

    /** A required integer field, recording an error when it is not one. */
    public function intField(string $field, string $label): int
    {
        $n = Str::toInt($this->str($field));
        if ($n === null) {
            $this->bad($field, $label);
            return 0;
        }
        return $n;
    }

    /** A monetary value; an empty value counts as zero when optional. */
    public function money(string $field, string $label, bool $required): Money
    {
        $raw = $this->str($field);
        if ($raw === '' && !$required) {
            return Money::zero();
        }
        $money = Money::parse($raw);
        if ($money === null) {
            $this->bad($field, $label);
            return Money::zero();
        }
        return $money;
    }

    public function percent(string $field, string $label, bool $required): Percent
    {
        $raw = $this->str($field);
        if ($raw === '' && !$required) {
            return Percent::zero();
        }
        $percent = Percent::parse($raw);
        if ($percent === null) {
            $this->bad($field, $label);
            return Percent::zero();
        }
        return $percent;
    }

    /** Parses an HTML date input (YYYY-MM-DD) in the given zone. Empty yields null. */
    public function date(string $field, string $label, DateTimeZone $zone): ?DateTimeImmutable
    {
        $raw = $this->str($field);
        if ($raw === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $zone);
        if ($date === false || $date->format('Y-m-d') !== $raw) {
            $this->bad($field, $label);
            return null;
        }
        return $date;
    }

    public function bad(string $field, string $label): void
    {
        $this->errors->add($field, $label . ' inválido');
    }

    /** @throws ValidationException when any value could not be understood */
    public function throwIfInvalid(): void
    {
        $this->errors->throwIfAny();
    }
}

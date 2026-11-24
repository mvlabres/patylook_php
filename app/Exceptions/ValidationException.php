<?php

declare(strict_types=1);

namespace Patylook\Exceptions;

/**
 * Field-level validation messages. The empty key holds messages that do not belong to a specific
 * field. It doubles as a collector: add messages, then call throwIfAny().
 */
final class ValidationException extends AppException
{
    /** @var array<string, string> */
    private array $fields = [];

    public function __construct()
    {
        parent::__construct('validação falhou');
    }

    public static function single(string $field, string $message): self
    {
        $e = new self();
        $e->add($field, $message);
        return $e;
    }

    /** Records a message for a field, keeping the first one reported. */
    public function add(string $field, string $message): void
    {
        if (!array_key_exists($field, $this->fields)) {
            $this->fields[$field] = $message;
        }
        $this->message = $this->summary();
    }

    /** Copies the messages of another instance (the first message of each field wins). */
    public function merge(self $other): void
    {
        foreach ($other->fields() as $field => $message) {
            $this->add($field, $message);
        }
    }

    /** @return array<string, string> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function has(string $field): bool
    {
        return ($this->fields[$field] ?? '') !== '';
    }

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }

    public function throwIfAny(): void
    {
        if (!$this->isEmpty()) {
            throw $this;
        }
    }

    /** Messages joined in field-name order, as a single line. */
    private function summary(): string
    {
        $keys = array_keys($this->fields);
        sort($keys, SORT_STRING);
        return implode('; ', array_map(fn (string $k): string => $this->fields[$k], $keys));
    }
}

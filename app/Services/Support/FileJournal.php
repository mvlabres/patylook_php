<?php

declare(strict_types=1);

namespace Patylook\Services\Support;

/** Names of files written during an operation, so they can be removed if it fails. */
final class FileJournal
{
    /** @var list<string> */
    private array $names = [];

    public function add(string $name): void
    {
        $this->names[] = $name;
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->names;
    }
}

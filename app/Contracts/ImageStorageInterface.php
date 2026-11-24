<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Dto\StoredFile;

/** Keeps image files, addressed by product folder and file name. */
interface ImageStorageInterface
{
    /** Writes (or atomically replaces) a file, creating the folder when needed. */
    public function save(string $folder, string $name, string $data): void;

    /** Deletes a file; a missing file is not an error. Empty folders are removed too. */
    public function remove(string $folder, string $name): void;

    /** @throws \Patylook\Exceptions\NotFoundException */
    public function open(string $folder, string $name): StoredFile;
}

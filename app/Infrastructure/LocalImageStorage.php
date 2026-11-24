<?php

declare(strict_types=1);

namespace Patylook\Infrastructure;

use DateTimeImmutable;
use Patylook\Contracts\ImageStorageInterface;
use Patylook\Dto\StoredFile;
use Patylook\Exceptions\NotFoundException;
use RuntimeException;

/** Keeps files under root/<folder>/<name> on the local file system. */
final class LocalImageStorage implements ImageStorageInterface
{
    private readonly string $root;

    public function __construct(string $root)
    {
        if (!is_dir($root) && !@mkdir($root, 0o755, true) && !is_dir($root)) {
            throw new RuntimeException('criar pasta de imagens ' . $root);
        }
        $real = realpath($root);
        $this->root = $real === false ? $root : $real;
    }

    /** The absolute directory holding the product folders. */
    public function root(): string
    {
        return $this->root;
    }

    public function save(string $folder, string $name, string $data): void
    {
        [$dir, $file] = $this->resolve($folder, $name);
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException('não foi possível criar a pasta ' . $dir);
        }
        // Write to a temporary file and rename, so readers never see a partial image.
        $tmp = @tempnam($dir, '.upload-');
        if ($tmp === false) {
            throw new RuntimeException('não foi possível criar arquivo temporário em ' . $dir);
        }
        if (@file_put_contents($tmp, $data) === false) {
            @unlink($tmp);
            throw new RuntimeException('não foi possível gravar a imagem em ' . $dir);
        }
        @chmod($tmp, 0o644);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('não foi possível gravar a imagem em ' . $dir);
        }
    }

    public function remove(string $folder, string $name): void
    {
        [$dir, $file] = $this->resolve($folder, $name);
        if (is_file($file) && !@unlink($file)) {
            throw new RuntimeException('não foi possível remover ' . $file);
        }
        if (is_dir($dir)) {
            @rmdir($dir); // succeeds only when the folder is empty
        }
    }

    public function open(string $folder, string $name): StoredFile
    {
        [, $file] = $this->resolve($folder, $name);
        if (!is_file($file)) {
            throw new NotFoundException();
        }
        $mtime = @filemtime($file);
        $size = @filesize($file);
        if ($mtime === false || $size === false) {
            throw new NotFoundException();
        }
        return new StoredFile($file, (new DateTimeImmutable('@' . $mtime)), $size);
    }

    /**
     * Validates both segments, so a crafted folder or name can never leave the root.
     *
     * @return array{0: string, 1: string} directory and file path
     */
    private function resolve(string $folder, string $name): array
    {
        foreach ([$folder, $name] as $segment) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $segment) !== 1 || $segment === '..') {
                throw new NotFoundException();
            }
        }
        $dir = $this->root . DIRECTORY_SEPARATOR . $folder;
        return [$dir, $dir . DIRECTORY_SEPARATOR . $name];
    }
}

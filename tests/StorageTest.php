<?php

declare(strict_types=1);

namespace Patylook\Tests;

use Patylook\Exceptions\NotFoundException;
use Patylook\Infrastructure\LocalImageStorage;

final class StorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pl_store_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->rmTree($this->root);
        $this->rmTree($this->root . '_outside');
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->rmTree($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testSaveOpenRemove(): void
    {
        $store = new LocalImageStorage($this->root);
        $store->save('CAM-1', 'principal.jpg', 'first');
        $store->save('CAM-1', 'principal.jpg', 'second'); // replaces atomically
        $file = $store->open('CAM-1', 'principal.jpg');
        $this->assertSame('second', file_get_contents($file->path));
        $this->assertSame(6, $file->size);
        $this->assertSame([], glob($this->root . '/CAM-1/.upload-*') ?: [], 'sem temporários');

        $store->remove('CAM-1', 'principal.jpg');
        $store->remove('CAM-1', 'principal.jpg'); // missing file is not an error
        $this->assertFalse(is_dir($this->root . '/CAM-1'), 'pasta vazia removida');
        $this->assertThrows(NotFoundException::class, fn () => $store->open('CAM-1', 'principal.jpg'));
    }

    public function testRemoveKeepsFolderWithOtherFiles(): void
    {
        $store = new LocalImageStorage($this->root);
        $store->save('A', 'principal.jpg', 'x');
        $store->save('A', 'imagem-01.jpg', 'y');
        $store->remove('A', 'imagem-01.jpg');
        $this->assertTrue(is_file($this->root . '/A/principal.jpg'));
    }

    public function testPathTraversalIsRejected(): void
    {
        $store = new LocalImageStorage($this->root);
        mkdir($this->root . '_outside');
        file_put_contents($this->root . '_outside/secret.jpg', 'secret');
        $bad = [
            ['..', 'x.jpg'], ['A', '..'], ['../' . basename($this->root) . '_outside', 'secret.jpg'], ['A/B', 'x.jpg'],
            ['A', '../x.jpg'], ['.hidden', 'x.jpg'], ['A', '.hidden'], ['', 'x.jpg'], ['A', ''], ['A\\B', 'x.jpg'], ["A\n", 'x.jpg'],
        ];
        foreach ($bad as [$folder, $name]) {
            $this->assertThrows(NotFoundException::class, fn () => $store->open($folder, $name), "open('$folder','$name')");
            $this->assertThrows(NotFoundException::class, fn () => $store->save($folder, $name, 'x'), "save('$folder','$name')");
            $this->assertThrows(NotFoundException::class, fn () => $store->remove($folder, $name), "remove('$folder','$name')");
        }
        $this->assertSame('secret', file_get_contents($this->root . '_outside/secret.jpg'));
    }

    public function testOpenDirectoryIsNotFound(): void
    {
        $store = new LocalImageStorage($this->root);
        $store->save('A', 'principal.jpg', 'x');
        mkdir($this->root . '/A/sub');
        $this->assertThrows(NotFoundException::class, fn () => $store->open('A', 'sub'));
    }
}

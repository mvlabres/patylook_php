<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\ImageStorageInterface;
use Patylook\Contracts\LoggerInterface;
use Patylook\Contracts\ProductImageRepositoryInterface;
use Patylook\Contracts\ProductImageServiceInterface;
use Patylook\Contracts\ProductRepositoryInterface;
use Patylook\Contracts\TransactionManagerInterface;
use Patylook\Dto\ProductImages;
use Patylook\Dto\StoredFile;
use Patylook\Exceptions\NotFoundException;
use Patylook\Exceptions\ValidationException;
use Patylook\Models\ImagePaths;
use Patylook\Models\User;
use Patylook\Services\Support\FileJournal;
use Throwable;

final class ProductImageService implements ProductImageServiceInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductImageRepositoryInterface $images,
        private readonly ImageStorageInterface $storage,
        private readonly ProductImageStore $imageStore,
        private readonly TransactionManagerInterface $tx,
        private readonly LoggerInterface $log,
    ) {
    }

    public function list(int $productId): array
    {
        return $this->images->listByProduct($productId);
    }

    public function add(User $actor, int $productId, ProductImages $images): void
    {
        $product = $this->products->findById($productId);
        if ($images->main === null && $images->extra === []) {
            throw ValidationException::single('images', 'Selecione ao menos uma imagem');
        }
        $existing = $this->images->listByProduct($productId);
        $mainRegistered = false;
        foreach ($existing as $image) {
            $mainRegistered = $mainRegistered || $image->isMain();
        }
        if (!$mainRegistered && $images->main === null) {
            throw ValidationException::single('main_image', 'Envie primeiro a imagem principal (principal.jpg) do produto');
        }
        $folder = ImagePaths::folderFor($product->sku);
        if ($folder === null) {
            throw ValidationException::single('images', 'SKU não gera um nome de pasta válido');
        }
        $files = $this->imageStore->prepare($images, $existing);

        $written = new FileJournal();
        try {
            $this->tx->run(function () use ($actor, $productId, $folder, $files, $mainRegistered, $written): void {
                $this->imageStore->store($actor, $productId, $folder, $files, $mainRegistered, $written);
            });
        } catch (Throwable $e) {
            $this->imageStore->removeFiles($folder, $written->names());
            throw ProductImageStore::translateConflict($e);
        }
    }

    public function delete(int $productId, int $imageId): void
    {
        $image = $this->images->findById($imageId);
        if ($image->productId !== $productId) {
            throw new NotFoundException();
        }
        if ($image->isMain()) {
            throw ValidationException::single('', 'A imagem principal não pode ser removida; envie outra para substituí-la');
        }
        $this->images->delete($imageId);
        // The row is gone; a leftover file is harmless, so a failure here is only logged.
        try {
            $this->storage->remove(ImagePaths::folderFromPath($image->path), $image->fileName);
        } catch (Throwable $e) {
            $this->log->warning('remover arquivo de imagem', ['path' => $image->path, 'err' => $e->getMessage()]);
        }
    }

    public function open(string $folder, string $name): StoredFile
    {
        return $this->storage->open($folder, $name);
    }
}

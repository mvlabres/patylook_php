<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\ImageProcessorInterface;
use Patylook\Contracts\ImageStorageInterface;
use Patylook\Contracts\LoggerInterface;
use Patylook\Contracts\ProductImageRepositoryInterface;
use Patylook\Dto\PreparedImage;
use Patylook\Dto\ProductImages;
use Patylook\Exceptions\ConflictException;
use Patylook\Exceptions\InvalidImageException;
use Patylook\Exceptions\ValidationException;
use Patylook\Models\ImagePaths;
use Patylook\Models\ProductImage;
use Patylook\Models\User;
use Patylook\Services\Support\FileJournal;
use Throwable;

/**
 * The image rules shared by the product use cases: validating and normalizing uploads, numbering
 * carousel files, registering their rows and writing them to disk.
 */
final class ProductImageStore
{
    public function __construct(
        private readonly ProductImageRepositoryInterface $images,
        private readonly ImageStorageInterface $storage,
        private readonly ImageProcessorInterface $processor,
        private readonly LoggerInterface $log,
    ) {
    }

    /**
     * Validates and normalizes every upload. $existing lists the images the product already has,
     * so new carousel files continue the numbering. Errors are reported per form field
     * ("main_image" and "images").
     *
     * @param list<ProductImage> $existing
     * @return list<PreparedImage>
     * @throws ValidationException
     */
    public function prepare(ProductImages $uploads, array $existing): array
    {
        $v = new ValidationException();
        $out = [];

        if ($uploads->main !== null) {
            try {
                $out[] = new PreparedImage(ImagePaths::MAIN_NAME, $this->processor->normalize($uploads->main->data), 0, true);
            } catch (InvalidImageException $e) {
                $v->add('main_image', $uploads->main->filename . ': ' . $e->getMessage());
            }
        }

        $extras = 0;
        $next = 1;
        foreach ($existing as $image) {
            if ($image->isMain()) {
                continue;
            }
            $extras++;
            if (preg_match('/^imagem-(\d+)\.jpg$/D', $image->fileName, $m) === 1 && (int) $m[1] >= $next) {
                $next = (int) $m[1] + 1;
            }
        }
        if ($extras + count($uploads->extra) > ImagePaths::MAX_EXTRA) {
            $v->add('images', sprintf('Máximo de %d imagens adicionais por produto', ImagePaths::MAX_EXTRA));
        } else {
            foreach ($uploads->extra as $upload) {
                try {
                    $data = $this->processor->normalize($upload->data);
                } catch (InvalidImageException $e) {
                    $v->add('images', $upload->filename . ': ' . $e->getMessage());
                    continue;
                }
                $out[] = new PreparedImage(sprintf('imagem-%02d.jpg', $next), $data, $next, false);
                $next++;
            }
        }
        $v->throwIfAny();
        return $out;
    }

    /**
     * Registers the rows and writes the files. It must run inside a transaction: a failure rolls
     * the rows back, and the caller removes the files listed in $written. A main image whose row
     * already exists is only overwritten on disk.
     *
     * @param list<PreparedImage> $files
     */
    public function store(User $actor, int $productId, string $folder, array $files, bool $mainRegistered, FileJournal $written): void
    {
        foreach ($files as $file) {
            $overwrite = $file->isMain && $mainRegistered;
            if (!$overwrite) {
                $this->images->create(new ProductImage(
                    productId: $productId,
                    fileName: $file->name,
                    path: ImagePaths::path($folder, $file->name),
                    sortOrder: $file->sortOrder,
                    createdBy: $actor->id,
                ));
            }
            try {
                $this->storage->save($folder, $file->name, $file->data);
            } catch (Throwable $e) {
                throw new \RuntimeException('gravar imagem ' . $file->name . ': ' . $e->getMessage(), 0, $e);
            }
            if (!$overwrite) {
                $written->add($file->name);
            }
        }
    }

    /** @param list<string> $names */
    public function removeFiles(string $folder, array $names): void
    {
        foreach ($names as $name) {
            try {
                $this->storage->remove($folder, $name);
            } catch (Throwable $e) {
                $this->log->warning('remover arquivo de imagem órfão', ['folder' => $folder, 'file' => $name, 'err' => $e->getMessage()]);
            }
        }
    }

    /** Reports a unique-key collision of image rows (two SKUs mapping to the same folder). */
    public static function translateConflict(Throwable $e): Throwable
    {
        if ($e instanceof ConflictException) {
            return ValidationException::single('main_image', 'Já existe um produto com a mesma pasta de imagens (SKU equivalente)');
        }
        return $e;
    }
}

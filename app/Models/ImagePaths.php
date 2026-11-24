<?php

declare(strict_types=1);

namespace Patylook\Models;

/** Naming rules of product image files and folders. */
final class ImagePaths
{
    /** Path prefix stored in product_images.path. A product folder is BASE/<folder>. */
    public const BASE = 'images/products';

    /** Fixed file name of a product's main image (the showcase banner). */
    public const MAIN_NAME = 'principal.jpg';

    /** Cap on the carousel images (besides the main one) per product. */
    public const MAX_EXTRA = 8;

    /** Largest accepted upload per image, in bytes. */
    public const MAX_BYTES = 6 << 20;

    private function __construct()
    {
    }

    /**
     * Derives the folder name for a SKU: characters that are unsafe in file system paths become
     * "-". Different SKUs may collapse to the same folder ("A/B" and "A B"); the unique index on
     * product_images.path rejects such a collision. Null when nothing usable is left.
     */
    public static function folderFor(string $sku): ?string
    {
        $folder = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($sku));
        $folder = trim((string) $folder, '-.');
        return $folder === '' ? null : $folder;
    }

    /** The stored path of a file inside a product folder. */
    public static function path(string $folder, string $fileName): string
    {
        return self::BASE . '/' . $folder . '/' . $fileName;
    }

    /** Extracts the product folder from a stored path. */
    public static function folderFromPath(string $path): string
    {
        $prefix = self::BASE . '/';
        $rest = str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
        $slash = strpos($rest, '/');
        return $slash === false ? $rest : substr($rest, 0, $slash);
    }
}

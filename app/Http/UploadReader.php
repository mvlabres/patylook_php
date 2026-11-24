<?php

declare(strict_types=1);

namespace Patylook\Http;

use Patylook\Core\Request;
use Patylook\Dto\ImageUpload;
use Patylook\Dto\ProductImages;
use Patylook\Exceptions\ValidationException;
use Patylook\Models\ImagePaths;

/** Collects the image files of a multipart form. Empty file parts (a blank file input) are ignored. */
final class UploadReader
{
    /**
     * Problems are added to $errors under the field they belong to ("main_image" or "images").
     */
    public function read(Request $request, ValidationException $errors): ProductImages
    {
        $files = $request->files();

        $main = null;
        $mainFiles = self::flatten($files['main_image'] ?? null);
        if ($mainFiles !== []) {
            $main = $this->load($mainFiles[0], 'main_image', $errors);
        }

        $extra = [];
        foreach (self::flatten($files['images'] ?? null) as $file) {
            $upload = $this->load($file, 'images', $errors);
            if ($upload !== null) {
                $extra[] = $upload;
            }
        }
        return new ProductImages($main, $extra);
    }

    /**
     * @param array{name: string, tmp_name: string, error: int, size: int} $file
     */
    private function load(array $file, string $field, ValidationException $errors): ?ImageUpload
    {
        $name = $file['name'];
        if ($file['error'] === UPLOAD_ERR_NO_FILE || $name === '') {
            return null;
        }
        $tooBig = sprintf('%s: imagem acima do limite de %d MB', $name, ImagePaths::MAX_BYTES >> 20);
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > ImagePaths::MAX_BYTES) {
            $errors->add($field, $tooBig);
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors->add($field, $name . ': falha ao receber o arquivo');
            return null;
        }
        if ($file['size'] === 0) {
            return null;
        }
        $data = is_uploaded_file($file['tmp_name']) ? @file_get_contents($file['tmp_name']) : false;
        if ($data === false) {
            $errors->add($field, $name . ': falha ao ler o arquivo');
            return null;
        }
        return new ImageUpload($name, $data);
    }

    /**
     * Normalizes PHP's $_FILES layout (single file or name[] arrays) into a list of files.
     *
     * @return list<array{name: string, tmp_name: string, error: int, size: int}>
     */
    private static function flatten(mixed $spec): array
    {
        if (!is_array($spec) || !isset($spec['name'], $spec['tmp_name'], $spec['error'], $spec['size'])) {
            return [];
        }
        if (!is_array($spec['name'])) {
            return [[
                'name' => (string) $spec['name'],
                'tmp_name' => (string) $spec['tmp_name'],
                'error' => (int) $spec['error'],
                'size' => (int) $spec['size'],
            ]];
        }
        $out = [];
        foreach (array_keys($spec['name']) as $i) {
            $out[] = [
                'name' => (string) $spec['name'][$i],
                'tmp_name' => (string) $spec['tmp_name'][$i],
                'error' => (int) $spec['error'][$i],
                'size' => (int) $spec['size'][$i],
            ];
        }
        return $out;
    }
}

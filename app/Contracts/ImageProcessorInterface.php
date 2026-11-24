<?php

declare(strict_types=1);

namespace Patylook\Contracts;

use Patylook\Exceptions\InvalidImageException;

interface ImageProcessorInterface
{
    /**
     * Validates an uploaded JPEG/PNG and returns it re-encoded as JPEG: orientation applied,
     * transparency flattened on white, longest side limited and all metadata dropped. The content,
     * not the file name or declared type, decides validity.
     *
     * @throws InvalidImageException with a message fit to show the user
     */
    public function normalize(string $data): string;
}

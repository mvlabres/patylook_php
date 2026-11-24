<?php

declare(strict_types=1);

namespace Patylook\Infrastructure;

use GdImage;
use Patylook\Contracts\ImageProcessorInterface;
use Patylook\Exceptions\InvalidImageException;
use Patylook\Models\ImagePaths;

/**
 * Validates an uploaded JPEG/PNG and re-encodes it as JPEG with GD: EXIF orientation applied,
 * transparency flattened on white, longest side limited and all metadata dropped.
 */
final class GdImageProcessor implements ImageProcessorInterface
{
    /** Guards against decompression bombs (checked before decoding). */
    private const MAX_PIXELS = 25_000_000;
    private const MIN_SIDE = 200;
    /** The longest side kept; larger images are scaled down. */
    private const MAX_SIDE = 1600;
    private const JPEG_QUALITY = 85;

    public function normalize(string $data): string
    {
        if ($data === '') {
            throw new InvalidImageException('arquivo de imagem inválido ou corrompido');
        }
        if (strlen($data) > ImagePaths::MAX_BYTES) {
            throw new InvalidImageException(sprintf('imagem acima do limite de %d MB', ImagePaths::MAX_BYTES >> 20));
        }

        $isJpeg = str_starts_with($data, "\xFF\xD8\xFF");
        $isPng = str_starts_with($data, "\x89PNG\r\n\x1a\n");
        if (!$isJpeg && !$isPng) {
            throw new InvalidImageException('formato não suportado: envie JPEG ou PNG');
        }
        $info = @getimagesizefromstring($data);
        if ($info === false) {
            throw new InvalidImageException('arquivo de imagem inválido ou corrompido');
        }
        [$width, $height, $type] = $info;
        if ($type !== IMAGETYPE_JPEG && $type !== IMAGETYPE_PNG) {
            throw new InvalidImageException('formato não suportado: envie JPEG ou PNG');
        }
        if ($width <= 0 || $height <= 0 || $width * $height > self::MAX_PIXELS) {
            throw new InvalidImageException('dimensões da imagem excedem o limite permitido');
        }
        if ($width < self::MIN_SIDE || $height < self::MIN_SIDE) {
            throw new InvalidImageException(sprintf('imagem muito pequena: mínimo de %dpx no menor lado', self::MIN_SIDE));
        }

        $this->ensureMemoryFor($width, $height);
        $source = $this->decode($data);

        [$newWidth, $newHeight] = self::scaledSize($width, $height);
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        if (!$canvas instanceof GdImage) {
            throw new InvalidImageException('arquivo de imagem inválido ou corrompido');
        }
        imagealphablending($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, (int) imagecolorallocate($canvas, 255, 255, 255));
        if ($newWidth === $width && $newHeight === $height) {
            imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        } else {
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        }

        if ($type === IMAGETYPE_JPEG) {
            $canvas = self::applyOrientation($canvas, self::jpegOrientation($data));
        }

        ob_start();
        $ok = imagejpeg($canvas, null, self::JPEG_QUALITY);
        $out = (string) ob_get_clean();
        if (!$ok || $out === '') {
            throw new InvalidImageException('não foi possível codificar a imagem');
        }
        return $out;
    }

    /**
     * Decodes the image. libgd is forgiving (and silent): a truncated file still yields a partly
     * gray picture. Incomplete uploads are refused up front, like any other corrupted file.
     */
    private function decode(string $data): GdImage
    {
        $complete = str_starts_with($data, "\xFF\xD8") ? self::jpegIsComplete($data) : self::pngIsComplete($data);
        $image = $complete ? @imagecreatefromstring($data) : false;
        if (!$image instanceof GdImage) {
            throw new InvalidImageException('arquivo de imagem inválido ou corrompido');
        }
        return $image;
    }

    /**
     * Walks the JPEG marker structure and reports whether it reaches the end-of-image marker.
     * Bytes after the end of image (some cameras append data) are ignored.
     */
    public static function jpegIsComplete(string $data): bool
    {
        $n = strlen($data);
        $pos = 2; // after SOI
        while ($pos + 2 <= $n) {
            if ($data[$pos] !== "\xFF") {
                return false;
            }
            $marker = ord($data[$pos + 1]);
            if ($marker === 0xFF) { // fill byte
                $pos++;
                continue;
            }
            if ($marker === 0xD9) { // end of image
                return true;
            }
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) { // markers without a length
                $pos += 2;
                continue;
            }
            if ($pos + 4 > $n) {
                return false;
            }
            $size = (ord($data[$pos + 2]) << 8) | ord($data[$pos + 3]);
            if ($size < 2) {
                return false;
            }
            $pos += 2 + $size;
            if ($marker === 0xDA) { // start of scan: skip the entropy-coded data up to the next real marker
                while (($ff = strpos($data, "\xFF", $pos)) !== false) {
                    $next = $ff + 1 < $n ? ord($data[$ff + 1]) : -1;
                    if ($next === 0x00 || ($next >= 0xD0 && $next <= 0xD7)) {
                        $pos = $ff + 2; // stuffed byte or restart marker, still inside the scan
                        continue;
                    }
                    $pos = $ff;
                    continue 2;
                }
                return false; // the data ended inside the scan
            }
        }
        return false;
    }

    /** Walks the PNG chunks and reports whether the IEND chunk is present. */
    public static function pngIsComplete(string $data): bool
    {
        $n = strlen($data);
        $pos = 8; // after the signature
        while ($pos + 12 <= $n) {
            $length = unpack('N', substr($data, $pos, 4))[1];
            if (substr($data, $pos + 4, 4) === 'IEND') {
                return true;
            }
            $pos += 12 + $length;
        }
        return false;
    }

    /** Shrinks (w, h) so the longest side is at most MAX_SIDE. */
    public static function scaledSize(int $w, int $h): array
    {
        $longest = max($w, $h);
        if ($longest <= self::MAX_SIDE) {
            return [$w, $h];
        }
        return [max(1, intdiv($w * self::MAX_SIDE, $longest)), max(1, intdiv($h * self::MAX_SIDE, $longest))];
    }

    /** Decoding allocates roughly five bytes per pixel; asks for more memory when it is not there. */
    private function ensureMemoryFor(int $width, int $height): void
    {
        $limit = ini_parse_quantity((string) ini_get('memory_limit'));
        if ($limit < 0) {
            return; // unlimited
        }
        $needed = $width * $height * 5 * 2 + memory_get_usage() + (16 << 20);
        if ($needed > $limit) {
            @ini_set('memory_limit', (string) $needed);
        }
    }

    /**
     * Reads the EXIF orientation tag (1-8) of a JPEG. Any parsing problem yields 1 (no
     * transformation): a malformed tag must never reject a valid image.
     */
    public static function jpegOrientation(string $data): int
    {
        $n = strlen($data);
        if ($n < 4 || $data[0] !== "\xFF" || $data[1] !== "\xD8") {
            return 1;
        }
        $pos = 2;
        while ($pos + 4 <= $n) {
            if ($data[$pos] !== "\xFF") {
                return 1;
            }
            $marker = ord($data[$pos + 1]);
            if ($marker === 0xFF) { // fill byte
                $pos++;
                continue;
            }
            if ($marker === 0xDA || $marker === 0xD9) { // start of scan / end of image
                return 1;
            }
            $size = (ord($data[$pos + 2]) << 8) | ord($data[$pos + 3]);
            if ($size < 2 || $pos + 2 + $size > $n) {
                return 1;
            }
            if ($marker === 0xE1) {
                $o = self::exifOrientation(substr($data, $pos + 4, $size - 2));
                if ($o !== 0) {
                    return $o;
                }
            }
            $pos += 2 + $size;
        }
        return 1;
    }

    /** Parses an APP1 payload ("Exif\0\0" + TIFF) and returns the orientation, or 0 when absent. */
    private static function exifOrientation(string $segment): int
    {
        if (strlen($segment) < 14 || substr($segment, 0, 6) !== "Exif\x00\x00") {
            return 0;
        }
        $tiff = substr($segment, 6);
        $byteOrder = substr($tiff, 0, 2);
        if ($byteOrder === 'II') {
            $u16 = fn (string $b): int => ord($b[0]) | (ord($b[1]) << 8);
            $u32 = fn (string $b): int => ord($b[0]) | (ord($b[1]) << 8) | (ord($b[2]) << 16) | (ord($b[3]) << 24);
        } elseif ($byteOrder === 'MM') {
            $u16 = fn (string $b): int => (ord($b[0]) << 8) | ord($b[1]);
            $u32 = fn (string $b): int => (ord($b[0]) << 24) | (ord($b[1]) << 16) | (ord($b[2]) << 8) | ord($b[3]);
        } else {
            return 0;
        }
        if ($u16(substr($tiff, 2, 2)) !== 0x002A) {
            return 0;
        }
        $ifd = $u32(substr($tiff, 4, 4));
        if ($ifd < 8 || $ifd + 2 > strlen($tiff)) {
            return 0;
        }
        $count = $u16(substr($tiff, $ifd, 2));
        for ($i = 0; $i < $count; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($entry + 12 > strlen($tiff)) {
                return 0;
            }
            if ($u16(substr($tiff, $entry, 2)) === 0x0112) { // Orientation
                $v = $u16(substr($tiff, $entry + 8, 2));
                return $v >= 1 && $v <= 8 ? $v : 0;
            }
        }
        return 0;
    }

    /** Rotates/flips the image according to an EXIF orientation value. */
    private static function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $rotate = static function (GdImage $img, int $clockwiseDegrees) use ($white): GdImage {
            // imagerotate turns counterclockwise.
            $rotated = imagerotate($img, (360 - $clockwiseDegrees) % 360, $white);
            return $rotated instanceof GdImage ? $rotated : $img;
        };
        $flip = static function (GdImage $img, int $mode): GdImage {
            imageflip($img, $mode);
            return $img;
        };

        return match ($orientation) {
            2 => $flip($image, IMG_FLIP_HORIZONTAL),
            3 => $rotate($image, 180),
            4 => $flip($image, IMG_FLIP_VERTICAL),
            5 => $flip($rotate($image, 90), IMG_FLIP_HORIZONTAL), // transpose
            6 => $rotate($image, 90),
            7 => $flip($rotate($image, 90), IMG_FLIP_VERTICAL), // transverse
            8 => $rotate($image, 270),
            default => $image,
        };
    }
}

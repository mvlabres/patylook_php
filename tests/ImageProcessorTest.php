<?php

declare(strict_types=1);

namespace Patylook\Tests;

use GdImage;
use Patylook\Exceptions\InvalidImageException;
use Patylook\Infrastructure\GdImageProcessor;
use Patylook\Models\ImagePaths;

final class ImageProcessorTest extends TestCase
{
    private GdImageProcessor $processor;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            $this->skip('extensão gd ausente');
        }
        $this->processor = new GdImageProcessor();
    }

    private function solid(int $w, int $h, int $r, int $g, int $b): GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagefilledrectangle($img, 0, 0, $w, $h, (int) imagecolorallocate($img, $r, $g, $b));
        return $img;
    }

    private function jpeg(GdImage $img): string
    {
        ob_start();
        imagejpeg($img, null, 95);
        return (string) ob_get_clean();
    }

    private function png(GdImage $img): string
    {
        ob_start();
        imagepng($img);
        return (string) ob_get_clean();
    }

    private function decode(string $data): GdImage
    {
        $img = imagecreatefromstring($data);
        $this->assertTrue($img instanceof GdImage, 'saída decodificável');
        return $img;
    }

    private function near(GdImage $img, int $x, int $y, int $r, int $g, int $b): bool
    {
        $c = imagecolorat($img, $x, $y);
        return abs((($c >> 16) & 255) - $r) < 40 && abs((($c >> 8) & 255) - $g) < 40 && abs(($c & 255) - $b) < 40;
    }

    /** Inserts an EXIF APP1 segment carrying the given orientation right after the JPEG's SOI marker. */
    private function withOrientation(string $jpeg, int $orientation): string
    {
        $tiff = "MM\x00\x2A\x00\x00\x00\x08" . "\x00\x01" . "\x01\x12\x00\x03\x00\x00\x00\x01" . pack('n', $orientation) . "\x00\x00" . "\x00\x00\x00\x00";
        $payload = "Exif\x00\x00" . $tiff;
        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($jpeg, 2);
    }

    public function testConvertsPngToJpeg(): void
    {
        $out = $this->processor->normalize($this->png($this->solid(400, 300, 255, 0, 0)));
        $this->assertSame("\xFF\xD8\xFF", substr($out, 0, 3));
        $img = $this->decode($out);
        $this->assertSame(400, imagesx($img));
        $this->assertSame(300, imagesy($img));
        $this->assertTrue($this->near($img, 200, 150, 255, 0, 0), 'cor preservada');
    }

    public function testFlattensTransparencyOnWhite(): void
    {
        $img = imagecreatetruecolor(300, 300);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, (int) imagecolorallocatealpha($img, 0, 0, 0, 127)); // fully transparent
        $out = $this->processor->normalize($this->png($img));
        $this->assertTrue($this->near($this->decode($out), 10, 10, 255, 255, 255), 'transparência vira branco, não preto');
    }

    public function testScalesDownKeepingProportion(): void
    {
        $out = $this->processor->normalize($this->jpeg($this->solid(3200, 2400, 0, 0, 255)));
        $img = $this->decode($out);
        $this->assertSame(1600, imagesx($img));
        $this->assertSame(1200, imagesy($img));
        $this->assertSame([1600, 1200], GdImageProcessor::scaledSize(3200, 2400));
        $this->assertSame([1200, 1600], GdImageProcessor::scaledSize(2400, 3200));
        $this->assertSame([800, 600], GdImageProcessor::scaledSize(800, 600));
    }

    public function testAppliesExifOrientation(): void
    {
        // 300x200 image, left half red and right half blue.
        $src = $this->solid(300, 200, 0, 0, 255);
        imagefilledrectangle($src, 0, 0, 149, 199, (int) imagecolorallocate($src, 255, 0, 0));
        $jpeg = $this->jpeg($src);

        $cases = [
            // name, orientation, width, height, x and y of a pixel that must be red
            ['unchanged', 1, 300, 200, 20, 100],
            ['mirrored', 2, 300, 200, 280, 100],
            ['rotate 180', 3, 300, 200, 280, 100],
            ['flip vertical', 4, 300, 200, 20, 100],
            ['transpose', 5, 200, 300, 100, 20],
            ['rotate 90 cw', 6, 200, 300, 100, 20],
            ['transverse', 7, 200, 300, 100, 280],
            ['rotate 90 ccw', 8, 200, 300, 100, 280],
        ];
        foreach ($cases as [$name, $orientation, $w, $h, $x, $y]) {
            $img = $this->decode($this->processor->normalize($this->withOrientation($jpeg, $orientation)));
            $this->assertSame([$w, $h], [imagesx($img), imagesy($img)], "$name: tamanho");
            $this->assertTrue($this->near($img, $x, $y, 255, 0, 0), "$name: pixel ($x,$y) deve ser vermelho");
        }
    }

    public function testOrientationParsing(): void
    {
        $jpeg = $this->jpeg($this->solid(300, 300, 0, 255, 0));
        $this->assertSame(1, GdImageProcessor::jpegOrientation($jpeg));
        $this->assertSame(6, GdImageProcessor::jpegOrientation($this->withOrientation($jpeg, 6)));
        $this->assertSame(1, GdImageProcessor::jpegOrientation($this->withOrientation($jpeg, 9)), 'valor inválido');
        $this->assertSame(1, GdImageProcessor::jpegOrientation('not a jpeg'));
    }

    public function testMalformedExifDoesNotRejectImage(): void
    {
        $jpeg = $this->jpeg($this->solid(300, 300, 0, 255, 0));
        $broken = substr($jpeg, 0, 2) . "\xFF\xE1\x00\x10Exif\x00\x00II\x2A\x00\xFF\xFF\xFF\xFF" . substr($jpeg, 2);
        $this->assertTrue($this->processor->normalize($broken) !== '');
    }

    public function testIncompleteFilesAreRejectedButValidVariantsPass(): void
    {
        $noise = imagecreatetruecolor(800, 600);
        for ($i = 0; $i < 3000; $i++) {
            imagesetpixel($noise, random_int(0, 799), random_int(0, 599), random_int(0, 0xFFFFFF));
        }
        $jpeg = $this->jpeg($noise);
        $png = $this->png($noise);
        foreach ([0.9, 0.5, 0.1] as $fraction) {
            $this->assertThrows(InvalidImageException::class, fn () => $this->processor->normalize(substr($jpeg, 0, (int) (strlen($jpeg) * $fraction))), "JPEG truncado em $fraction");
            $this->assertThrows(InvalidImageException::class, fn () => $this->processor->normalize(substr($png, 0, (int) (strlen($png) * $fraction))), "PNG truncado em $fraction");
        }
        // Valid variants: trailing data after the end of image, progressive scans, EXIF with a thumbnail-like payload.
        $this->assertTrue($this->processor->normalize($jpeg . str_repeat("\0", 2000) . 'MPF-like trailer') !== '');
        imageinterlace($noise, true);
        $this->assertTrue($this->processor->normalize($this->jpeg($noise)) !== '', 'JPEG progressivo');
        $this->assertTrue($this->processor->normalize($this->withOrientation($jpeg, 6)) !== '');
        $this->assertTrue(GdImageProcessor::jpegIsComplete($jpeg));
        $this->assertFalse(GdImageProcessor::jpegIsComplete(substr($jpeg, 0, -2)));
        $this->assertTrue(GdImageProcessor::pngIsComplete($png));
        $this->assertFalse(GdImageProcessor::pngIsComplete(substr($png, 0, -12)));
    }

    public function testRejections(): void
    {
        $cases = [
            'empty' => '',
            'text' => 'not an image at all',
            'gif' => 'GIF89a' . str_repeat("\0", 64),
            'truncated png' => substr($this->png($this->solid(300, 300, 255, 255, 255)), 0, 60),
            'tiny' => $this->png($this->solid(50, 50, 255, 255, 255)),
            'too big' => str_repeat('a', ImagePaths::MAX_BYTES + 1),
            'prepended payload' => '<script>' . $this->png($this->solid(300, 300, 255, 255, 255)),
        ];
        foreach ($cases as $name => $data) {
            $this->assertThrows(InvalidImageException::class, fn () => $this->processor->normalize($data), $name);
        }
        $e = $this->assertThrows(InvalidImageException::class, fn () => $this->processor->normalize('xx'));
        $this->assertSame('formato não suportado: envie JPEG ou PNG', $e->getMessage());
    }
}

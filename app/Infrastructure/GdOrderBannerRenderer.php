<?php

declare(strict_types=1);

namespace Patylook\Infrastructure;

use GdImage;
use Patylook\Contracts\OrderBannerRendererInterface;
use Patylook\Models\Customer;
use Patylook\Models\MainSeller;
use Patylook\Models\Order;
use RuntimeException;

/**
 * Draws the order summary as a portrait PNG in the site's colors (black and gold, Montserrat, the
 * VL Store logo on top). The image only exists in memory: callers stream it and nothing is saved.
 *
 * The layout runs twice over the same code: once on a 1x1 canvas just to learn the height (every
 * drawing call is clipped, text measuring still works), then for real on a canvas of that height.
 */
final class GdOrderBannerRenderer implements OrderBannerRendererInterface
{
    private const WIDTH = 1080;
    private const MIN_HEIGHT = 1350; // 4:5, what a phone shows without scrolling in a chat
    private const MARGIN = 64;

    public function __construct(private readonly string $fontsDir, private readonly string $logoPath)
    {
        if (!function_exists('imagettftext')) {
            throw new RuntimeException('a extensão GD com FreeType é necessária para gerar a imagem do pedido');
        }
    }

    public function render(Order $order, Customer $customer, ?MainSeller $seller): string
    {
        $probe = imagecreatetruecolor(1, 1);
        $height = max(self::MIN_HEIGHT, $this->layout($probe, 0, $order, $customer, $seller));

        $im = imagecreatetruecolor(self::WIDTH, $height);
        $this->layout($im, $height, $order, $customer, $seller);

        ob_start();
        imagepng($im, null, 6);
        return (string) ob_get_clean();
    }

    /** Draws everything and returns the y where the content ends (bottom padding included). */
    private function layout(GdImage $im, int $height, Order $o, Customer $c, ?MainSeller $s): int
    {
        imagealphablending($im, true);
        $text = imagecolorallocate($im, 241, 233, 220);
        $muted = imagecolorallocate($im, 163, 150, 130);
        $gold = imagecolorallocate($im, 212, 175, 106);
        $goldLight = imagecolorallocate($im, 240, 217, 168);
        $cardFill = imagecolorallocate($im, 26, 22, 17);
        $cardBorder = imagecolorallocate($im, 84, 71, 47);
        $strongBorder = imagecolorallocate($im, 150, 122, 72);
        $x0 = self::MARGIN;
        $x1 = self::WIDTH - self::MARGIN;
        $cw = $x1 - $x0;

        $this->background($im, $height);

        // Header: the logo, like the site's top bar.
        $logo = imagecreatefrompng($this->logoPath);
        if ($logo === false) {
            throw new RuntimeException('logomarca não encontrada: ' . $this->logoPath);
        }
        $lw = 500;
        $lh = (int) round(imagesy($logo) * $lw / imagesx($logo));
        imagecopyresampled($im, $logo, (int) ((self::WIDTH - $lw) / 2), 44, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
        $y = 44 + $lh + 26;
        $this->rule($im, $y, $gold);
        $y += 52;

        // Greeting.
        $greeting = sprintf('Olá %s, obrigado pela preferência. Seguem os detalhes da sua compra:', $c->firstName());
        foreach ($this->wrap($greeting, 38, 'Medium', $cw) as $line) {
            $y += 54;
            $this->text($im, 38, $x0, $y, $text, 'Medium', $line);
        }
        $y += 56;

        // Order data.
        $y = $this->sectionTitle($im, $y, 'Dados do pedido', sprintf('Pedido #%d', $o->id), $gold, $muted, $cw);
        $rows = [['Cliente', $c->name], ['Forma de pagamento', $o->paymentTypeName]];
        if ($o->installments !== null) {
            $rows[] = ['Parcelas', sprintf('%dx de %s', $o->installments, (string) $o->installmentValue())];
        }
        $wrapped = [];
        $cardH = 24;
        foreach ($rows as [$label, $value]) {
            $lines = $this->wrap($value, 32, 'SemiBold', $cw - 64);
            $wrapped[] = [$label, $lines];
            $cardH += 30 + count($lines) * 44 + 22;
        }
        $this->card($im, $x0, $y, $x1, $y + $cardH, 22, $cardFill, $cardBorder);
        $ry = $y + 24;
        foreach ($wrapped as [$label, $lines]) {
            $ry += 26;
            $this->text($im, 22, $x0 + 32, $ry, $muted, 'Medium', $label);
            foreach ($lines as $line) {
                $ry += 44;
                $this->text($im, 32, $x0 + 32, $ry, $text, 'SemiBold', $line);
            }
            $ry += 22;
        }
        $y += $cardH + 48;

        // Items.
        $y = $this->sectionTitle($im, $y, 'Itens', null, $gold, $muted, $cw);
        foreach ($o->items as $it) {
            $nameLines = $this->wrap($it->productName, 30, 'SemiBold', $cw - 64);
            $itemH = 26 + count($nameLines) * 44 + 22 + 66 + 36;
            $this->card($im, $x0, $y, $x1, $y + $itemH, 22, $cardFill, $cardBorder);
            $iy = $y + 26;
            foreach ($nameLines as $line) {
                $iy += 44;
                $this->text($im, 30, $x0 + 32, $iy, $text, 'SemiBold', $line);
            }
            $iy += 22;
            $labelY = $iy + 24;
            $valueY = $iy + 66;
            $colQty = $x0 + 32;
            $colDiscount = $x0 + 32 + 250;
            $this->text($im, 21, $colQty, $labelY, $muted, 'Medium', 'Quantidade');
            $this->text($im, 30, $colQty, $valueY, $text, 'Medium', (string) $it->quantity);
            $this->text($im, 21, $colDiscount, $labelY, $muted, 'Medium', 'Desconto');
            $this->text($im, 30, $colDiscount, $valueY, $text, 'Medium', (string) $it->discount);
            $this->textRight($im, 21, $x1 - 32, $labelY, $muted, 'Medium', 'Total');
            $this->textRight($im, 30, $x1 - 32, $valueY, $goldLight, 'Bold', (string) $it->total());
            $y += $itemH + 20;
        }
        $y += 28;

        // Totals: only the final amount.
        $this->card($im, $x0, $y, $x1, $y + 150, 26, $cardFill, $strongBorder);
        $label = 'Total do pedido';
        $this->text($im, 28, $x0 + 36, $y + 88, $muted, 'Medium', $label);
        $total = (string) $o->total;
        $size = 64;
        $room = $cw - 36 - 36 - $this->width(28, 'Medium', $label) - 24;
        while ($size > 30 && $this->width($size, 'Bold', $total) > $room) {
            $size -= 2;
        }
        $this->textRight($im, $size, $x1 - 36, $y + 88 + (int) (($size - 28) / 4), $goldLight, 'Bold', $total);
        $y += 150 + 56;

        // Footer: who is the contact for this sale.
        $this->rule($im, $y, $cardBorder);
        $y += 14;
        if ($s !== null) {
            $line = sprintf('Atendimento: %s · WhatsApp %s', $s->name, $s->whatsapp);
            foreach ($this->wrap($line, 24, 'Regular', $cw) as $part) {
                $y += 40;
                $this->textRight($im, 24, (int) (($x0 + $x1 + $this->width(24, 'Regular', $part)) / 2), $y, $muted, 'Regular', $part);
            }
        } else {
            $y += 40;
            $this->textRight($im, 24, (int) (($x0 + $x1 + $this->width(24, 'Regular', 'VL Store · Valentina Labres')) / 2), $y, $muted, 'Regular', 'VL Store · Valentina Labres');
        }
        return $y + 56;
    }

    private function background(GdImage $im, int $height): void
    {
        for ($i = 0; $i < $height; $i++) {
            $t = $height > 1 ? $i / ($height - 1) : 0;
            $shade = imagecolorallocate($im, (int) (21 - 12 * $t), (int) (18 - 10 * $t), (int) (14 - 8 * $t));
            imageline($im, 0, $i, self::WIDTH - 1, $i, $shade);
        }
        // A soft gold glow behind the logo, built from faint concentric ellipses.
        $glow = imagecolorallocatealpha($im, 120, 95, 50, 126);
        for ($r = 600; $r > 0; $r -= 14) {
            imagefilledellipse($im, (int) (self::WIDTH / 2), 40, $r * 2, (int) ($r * 0.9), $glow);
        }
    }

    /** A thin horizontal rule across the content width. */
    private function rule(GdImage $im, int $y, int $color): void
    {
        imageline($im, self::MARGIN, $y, self::WIDTH - self::MARGIN, $y, $color);
    }

    /** Section heading in gold with an optional note at the right; returns the y where the content starts. */
    private function sectionTitle(GdImage $im, int $y, string $title, ?string $note, int $gold, int $muted, int $cw): int
    {
        $baseline = $y + 30;
        $this->text($im, 28, self::MARGIN, $baseline, $gold, 'SemiBold', mb_strtoupper($title, 'UTF-8'));
        if ($note !== null) {
            $this->textRight($im, 24, self::MARGIN + $cw, $baseline, $muted, 'Medium', $note);
        }
        return $y + 58;
    }

    /** Rounded card: a border-colored shape with the fill inset by 2px (opaque colors only). */
    private function card(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $radius, int $fill, int $border): void
    {
        $this->roundRect($im, $x1, $y1, $x2, $y2, $radius, $border);
        $this->roundRect($im, $x1 + 2, $y1 + 2, $x2 - 2, $y2 - 2, $radius - 2, $fill);
    }

    private function roundRect(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }

    private function font(string $weight): string
    {
        $path = $this->fontsDir . DIRECTORY_SEPARATOR . 'Montserrat-' . $weight . '.ttf';
        if (!is_file($path)) {
            throw new RuntimeException('fonte não encontrada: ' . $path);
        }
        return $path;
    }

    private function text(GdImage $im, float $size, int $x, int $baseline, int $color, string $weight, string $str): void
    {
        imagettftext($im, $size, 0, $x, $baseline, $color, $this->font($weight), $str);
    }

    private function textRight(GdImage $im, float $size, int $right, int $baseline, int $color, string $weight, string $str): void
    {
        $this->text($im, $size, $right - $this->width($size, $weight, $str), $baseline, $color, $weight, $str);
    }

    private function width(float $size, string $weight, string $str): int
    {
        if ($str === '') {
            return 0;
        }
        $box = imagettfbbox($size, 0, $this->font($weight), $str);
        return $box === false ? 0 : $box[2] - $box[0];
    }

    /**
     * Breaks text into lines no wider than $maxWidth, at spaces; a single word wider than the line
     * is cut where it no longer fits.
     *
     * @return list<string>
     */
    private function wrap(string $str, float $size, string $weight, int $maxWidth): array
    {
        $lines = [];
        $current = '';
        foreach (preg_split('/\s+/u', trim($str), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $try = $current === '' ? $word : $current . ' ' . $word;
            if ($this->width($size, $weight, $try) <= $maxWidth) {
                $current = $try;
                continue;
            }
            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
            while (mb_strlen($current, 'UTF-8') > 1 && $this->width($size, $weight, $current) > $maxWidth) {
                $cut = mb_strlen($current, 'UTF-8') - 1;
                while ($cut > 1 && $this->width($size, $weight, mb_substr($current, 0, $cut, 'UTF-8')) > $maxWidth) {
                    $cut--;
                }
                $lines[] = mb_substr($current, 0, $cut, 'UTF-8');
                $current = mb_substr($current, $cut, null, 'UTF-8');
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines === [] ? [''] : $lines;
    }
}

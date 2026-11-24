<?php

declare(strict_types=1);

namespace Patylook\Models;

use Patylook\Support\Fixed2;
use Stringable;

/** A monetary amount in cents. Integers only: no floating point error is possible. */
final readonly class Money implements Stringable
{
    /** Keeps values inside MySQL DECIMAL(12,2): 9_999_999_999.99. */
    public const MAX = 999_999_999_999;

    public function __construct(public int $cents = 0)
    {
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /** Accepts "1234.56", "1234,56" and "1.234,56". Returns null when the text is not a number. */
    public static function parse(string $s): ?self
    {
        $v = Fixed2::parse($s);
        return $v === null ? null : new self($v);
    }

    public function plus(self $o): self
    {
        return new self($this->cents + $o->cents);
    }

    public function minus(self $o): self
    {
        return new self($this->cents - $o->cents);
    }

    public function times(int $qty): self
    {
        return new self($this->cents * $qty);
    }

    /** p% of this amount, rounded half away from zero. */
    public function percent(Percent $p): self
    {
        return new self(Fixed2::roundDiv($this->cents * $p->hundredths, 10000));
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function equals(self $o): bool
    {
        return $this->cents === $o->cents;
    }

    /** Plain decimal string ("12.50"), the format MySQL DECIMAL uses. */
    public function decimal(): string
    {
        return Fixed2::format($this->cents, '.');
    }

    /** Formatted for HTML inputs ("1234,56"). */
    public function input(): string
    {
        return Fixed2::format($this->cents, ',');
    }

    /** Brazilian Real ("R$ 1.234,56"). */
    public function __toString(): string
    {
        $v = $this->cents;
        $sign = '';
        if ($v < 0) {
            $sign = '-';
            $v = -$v;
        }
        return sprintf('%sR$ %s,%02d', $sign, Fixed2::groupThousands(intdiv($v, 100)), $v % 100);
    }
}

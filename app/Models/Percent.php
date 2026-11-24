<?php

declare(strict_types=1);

namespace Patylook\Models;

use Patylook\Support\Fixed2;
use Stringable;

/** A percentage in hundredths (12.50% is 1250). */
final readonly class Percent implements Stringable
{
    public function __construct(public int $hundredths = 0)
    {
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /** Accepts the same formats as Money::parse. Returns null when the text is not a number. */
    public static function parse(string $s): ?self
    {
        $v = Fixed2::parse($s);
        return $v === null ? null : new self($v);
    }

    public function isZero(): bool
    {
        return $this->hundredths === 0;
    }

    /** Plain decimal string ("12.50"), the format MySQL DECIMAL uses. */
    public function decimal(): string
    {
        return Fixed2::format($this->hundredths, '.');
    }

    /** Formatted for HTML inputs ("12,50"). */
    public function input(): string
    {
        return Fixed2::format($this->hundredths, ',');
    }

    public function __toString(): string
    {
        return $this->input() . '%';
    }
}

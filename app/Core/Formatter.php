<?php

declare(strict_types=1);

namespace Patylook\Core;

use DateTimeImmutable;
use DateTimeZone;
use Patylook\Models\Money;
use Patylook\Models\Percent;

/** Formats values for display in the templates. */
final class Formatter
{
    public function __construct(private readonly DateTimeZone $timezone)
    {
    }

    public function money(Money $m): string
    {
        return (string) $m;
    }

    public function moneyOrDash(?Money $m): string
    {
        return $m === null ? '—' : (string) $m;
    }

    public function percent(Percent $p): string
    {
        return (string) $p;
    }

    public function date(?DateTimeImmutable $d): string
    {
        return $d === null ? '—' : $d->format('d/m/Y');
    }

    /** A date for an HTML date input (YYYY-MM-DD). */
    public function dateInput(?DateTimeImmutable $d): string
    {
        return $d === null ? '' : $d->format('Y-m-d');
    }

    public function datetime(?DateTimeImmutable $d): string
    {
        return $d === null ? '—' : $d->setTimezone($this->timezone)->format('d/m/Y H:i');
    }

    /** 12345678901 -> 123.456.789-01 */
    public function cpf(string $s): string
    {
        if (strlen($s) !== 11) {
            return $s;
        }
        return substr($s, 0, 3) . '.' . substr($s, 3, 3) . '.' . substr($s, 6, 3) . '-' . substr($s, 9);
    }
}

<?php

declare(strict_types=1);

namespace Patylook\Repositories\Mysql;

use DateTimeImmutable;
use Patylook\Infrastructure\Database\Database;
use Patylook\Models\Money;
use Patylook\Models\Percent;

/** Shared plumbing of the MySQL repositories: connection access and column conversions. */
abstract class MysqlRepository
{
    public function __construct(protected readonly Database $db)
    {
    }

    /** Builds a LIKE "contains" pattern with the wildcards escaped. */
    protected static function likePattern(string $s): string
    {
        return '%' . strtr($s, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
    }

    /** A TIMESTAMP/DATETIME column (already in the session time zone) as a date-time; null for NULL. */
    protected function timestamp(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || str_starts_with((string) $value, '0000-00-00')) {
            return null;
        }
        return new DateTimeImmutable((string) $value, $this->db->timezone());
    }

    /** A DATE column as midnight in the application's time zone; null for NULL. */
    protected function date(mixed $value): ?DateTimeImmutable
    {
        $ts = $this->timestamp($value);
        return $ts?->setTime(0, 0);
    }

    /** Formats a date for a DATE parameter. */
    protected static function dateParam(DateTimeImmutable $d): string
    {
        return $d->format('Y-m-d');
    }

    /** A DECIMAL column as Money, without going through floats. */
    protected static function money(mixed $value): Money
    {
        $m = Money::parse((string) $value);
        if ($m === null) {
            throw new \UnexpectedValueException('decimal inválido: ' . var_export($value, true));
        }
        return $m;
    }

    protected static function percent(mixed $value): Percent
    {
        $p = Percent::parse((string) $value);
        if ($p === null) {
            throw new \UnexpectedValueException('decimal inválido: ' . var_export($value, true));
        }
        return $p;
    }

    /** Empty strings are stored as NULL. */
    protected static function nullString(string $s): ?string
    {
        return $s === '' ? null : $s;
    }

    /** Maps 0 to NULL, for optional foreign keys. */
    protected static function nullInt(int $i): ?int
    {
        return $i === 0 ? null : $i;
    }
}

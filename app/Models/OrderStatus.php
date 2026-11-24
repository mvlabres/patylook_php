<?php

declare(strict_types=1);

namespace Patylook\Models;

enum OrderStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Canceled = 'canceled';

    /** Portuguese display name. */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::Completed => 'Concluído',
            self::Canceled => 'Cancelado',
        };
    }

    /** Whether an order may move from this status to $next. */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Open => $next === self::Completed || $next === self::Canceled,
            self::Completed => $next === self::Canceled,
            self::Canceled => false,
        };
    }
}

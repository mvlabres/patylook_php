<?php

declare(strict_types=1);

namespace Patylook\Models;

/** How a payment type is paid; classifies the type only (no payment is processed). Stored in payment_type.category. */
enum PaymentCategory: string
{
    case Cash = 'cash';
    case Credit = 'credit';
    case Debit = 'debit';

    /** Portuguese display name. */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'À vista',
            self::Credit => 'Crédito',
            self::Debit => 'Débito',
        };
    }
}

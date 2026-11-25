<?php

declare(strict_types=1);

namespace Patylook\Models;

/** The gender a product is classified for. Stored in product.gender. */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Unisex = 'unisex';

    /** Portuguese display name. */
    public function label(): string
    {
        return match ($this) {
            self::Male => 'Masculino',
            self::Female => 'Feminino',
            self::Unisex => 'Unissex',
        };
    }
}

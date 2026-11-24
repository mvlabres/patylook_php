<?php

declare(strict_types=1);

namespace Patylook\Models;

use Patylook\Exceptions\ValidationException;
use Patylook\Support\Str;

/**
 * Only classifies how the customer intends to pay (and any surcharge);
 * there is no payment processing in this system.
 */
final class PaymentType
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public string $description = '',
        public Percent $surchargePercent = new Percent(0),
    ) {
    }

    public function normalize(): void
    {
        $this->name = Str::trim($this->name);
        $this->description = Str::trim($this->description);
    }

    /** @throws ValidationException */
    public function validate(): void
    {
        $v = new ValidationException();
        Rules::requireText($v, 'name', 'Nome', $this->name, 45);
        Rules::optionalText($v, 'description', 'Descrição', $this->description, 45);
        if ($this->surchargePercent->hundredths < 0 || $this->surchargePercent->hundredths > 99_999) {
            $v->add('surcharge_percent', 'Acréscimo deve estar entre 0 e 999,99');
        }
        $v->throwIfAny();
    }
}

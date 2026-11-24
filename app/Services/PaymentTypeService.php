<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\PaymentTypeRepositoryInterface;
use Patylook\Contracts\PaymentTypeServiceInterface;
use Patylook\Models\PaymentType;
use Patylook\Services\Support\ConflictTranslator;

final class PaymentTypeService implements PaymentTypeServiceInterface
{
    private const CONFLICTS = ['name' => 'Já existe uma forma de pagamento com esse nome'];

    public function __construct(private readonly PaymentTypeRepositoryInterface $types)
    {
    }

    public function list(): array
    {
        return $this->types->list();
    }

    public function get(int $id): PaymentType
    {
        return $this->types->findById($id);
    }

    public function save(PaymentType $type): void
    {
        $type->normalize();
        $type->validate();
        ConflictTranslator::run(function () use ($type): void {
            if ($type->id === 0) {
                $this->types->create($type);
            } else {
                $this->types->update($type);
            }
        }, self::CONFLICTS);
    }

    public function delete(int $id): void
    {
        $this->types->delete($id);
    }
}

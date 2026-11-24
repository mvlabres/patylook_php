<?php

declare(strict_types=1);

namespace Patylook\Models;

use DateTimeImmutable;
use Patylook\Exceptions\ValidationException;
use Patylook\Support\Fixed2;

/** A price entry for a product, valid for a date range. */
final class Pricebook
{
    /** Keeps the markup inside MySQL DECIMAL(6,2): 9999.99%. */
    public const MAX_MARKUP = 999_999;

    public function __construct(
        public int $id = 0,
        public int $productId = 0,
        public Money $productCost = new Money(0),
        public Percent $markupPercent = new Percent(0),
        public Money $packagingCost = new Money(0),
        public Money $logisticsCost = new Money(0),
        public Money $otherCosts = new Money(0),
        public Money $finalPrice = new Money(0),
        public ?DateTimeImmutable $validFrom = null, // date only
        public ?DateTimeImmutable $validTo = null,
        public int $createdBy = 0,
        public string $createdByName = '',
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }

    /** Everything the business spends to sell one unit. */
    public function totalCost(): Money
    {
        return $this->productCost->plus($this->packagingCost)->plus($this->logisticsCost)->plus($this->otherCosts);
    }

    /**
     * Derives the markup from the informed final price: the reverse of "final price = total cost +
     * markup", i.e. (final - cost) / cost. Without any cost there is nothing to apply a percentage
     * to, so the markup is 0.
     */
    public function computeMarkup(): void
    {
        $cost = $this->totalCost()->cents;
        if ($cost <= 0) {
            $this->markupPercent = new Percent(0);
            return;
        }
        $this->markupPercent = new Percent(Fixed2::roundDiv(($this->finalPrice->cents - $cost) * 10000, $cost));
    }

    /**
     * Checks the informed values and, when they are usable, fills the markup.
     *
     * @throws ValidationException
     */
    public function validate(): void
    {
        $v = new ValidationException();
        if ($this->productCost->isNegative()) {
            $v->add('product_cost', 'Custo do produto não pode ser negativo');
        }
        if ($this->packagingCost->isNegative()) {
            $v->add('packaging_cost', 'Custo de embalagem não pode ser negativo');
        }
        if ($this->logisticsCost->isNegative()) {
            $v->add('logistics_cost', 'Custo logístico não pode ser negativo');
        }
        if ($this->otherCosts->isNegative()) {
            $v->add('other_costs', 'Outros custos não podem ser negativos');
        }
        if ($this->validFrom === null) {
            $v->add('valid_from', 'Início da vigência é obrigatório');
        }
        if ($this->validTo !== null && $this->validFrom !== null && $this->validTo < $this->validFrom) {
            $v->add('valid_to', 'Fim da vigência deve ser igual ou posterior ao início');
        }

        $costsOk = !$v->has('product_cost') && !$v->has('packaging_cost')
            && !$v->has('logistics_cost') && !$v->has('other_costs');
        $total = $this->totalCost();
        if ($this->finalPrice->isNegative()) {
            $v->add('final_price', 'Preço final não pode ser negativo');
        } elseif ($this->finalPrice->cents > Money::MAX) {
            $v->add('final_price', 'Preço final excede o limite permitido');
        } elseif ($costsOk && $total->cents > Money::MAX) {
            $v->add('product_cost', 'Valor total dos custos excede o limite permitido');
        } elseif ($costsOk && $this->finalPrice->cents < $total->cents) {
            $v->add('final_price', 'Preço final não pode ser menor que o custo total (' . $total . ')');
        } elseif ($costsOk) {
            $this->computeMarkup();
            if ($this->markupPercent->hundredths > self::MAX_MARKUP) {
                $v->add('final_price', 'Preço final muito acima do custo: o markup calculado passa de 9999,99%');
            }
        }
        $v->throwIfAny();
    }
}

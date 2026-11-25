<?php

declare(strict_types=1);

namespace Patylook\Models;

use DateTimeImmutable;
use Patylook\Exceptions\ValidationException;
use Patylook\Support\Str;

final class Product
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public string $description = '',
        public string $sku = '',
        public int $categoryId = 0,
        public string $categoryName = '',
        public int $brandId = 0, // 0 for products registered before brands existed
        public string $brandName = '',
        /** Null only while a form has not chosen one; stored products always have a gender. */
        public ?Gender $gender = null,
        public int $stock = 0,
        public bool $isActive = false,
        public int $createdBy = 0,
        public ?DateTimeImmutable $createdAt = null,
        /** The price valid today, when one exists. Read-only projection. */
        public ?Money $currentPrice = null,
        /** Sum of every registered cost (product, packaging, logistics, other) of the current price entry. */
        public ?Money $currentCost = null,
        /** The markup of the current price entry, as registered. */
        public ?Percent $currentMarkup = null,
    ) {
    }

    /** Real gain per unit: the current price minus all registered costs. Null without a current price. */
    public function realGain(): ?Money
    {
        return $this->currentPrice === null || $this->currentCost === null ? null : $this->currentPrice->minus($this->currentCost);
    }

    public function normalize(): void
    {
        $this->name = Str::trim($this->name);
        $this->description = Str::trim($this->description);
        $this->sku = Str::upper(Str::trim($this->sku));
    }

    /**
     * Checks the fields the user types. The SKU is never typed: it is generated when the product
     * is created and kept afterwards, so only its length is checked here.
     *
     * @throws ValidationException
     */
    public function validate(): void
    {
        $v = new ValidationException();
        Rules::requireText($v, 'name', 'Nome', $this->name, 150);
        Rules::optionalText($v, 'description', 'Descrição', $this->description, 500);
        Rules::optionalText($v, 'sku', 'SKU', $this->sku, 45);
        if ($this->categoryId <= 0) {
            $v->add('product_category_id', 'Categoria é obrigatória');
        }
        if ($this->brandId < 0) {
            $v->add('brand_id', 'Marca inválida');
        }
        if ($this->gender === null) {
            $v->add('gender', 'Gênero é obrigatório');
        }
        if ($this->stock < 0) {
            $v->add('stock', 'Estoque não pode ser negativo');
        }
        $v->throwIfAny();
    }

    /**
     * Builds the SKU of a new product: the first three letters of the category and of the brand
     * plus the creation date and time (ddMMyyHHmm), e.g. PER-FER-2411261602.
     */
    public static function generateSku(string $category, string $brand, DateTimeImmutable $at): string
    {
        return self::skuPrefix($category) . '-' . self::skuPrefix($brand) . '-' . $at->format('dmyHi');
    }

    private const ACCENTS = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y',
    ];

    /**
     * Up to three upper-case letters or digits of $s, ignoring accents, spaces and punctuation.
     * A name without any yields "XXX" so the SKU keeps its shape.
     */
    private static function skuPrefix(string $s): string
    {
        $folded = strtr(Str::upper($s), self::ACCENTS);
        $clean = (string) preg_replace('/[^A-Z0-9]/', '', $folded);
        $clean = substr($clean, 0, 3);
        return $clean === '' ? 'XXX' : $clean;
    }
}

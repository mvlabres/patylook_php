<?php
/**
 * One product line of the order form.
 *
 * @var list<\Patylook\Models\Product> $products
 * @var int $productId
 * @var string $quantity
 * @var string $discount
 * @var \Patylook\Core\Formatter $fmt
 */
?>
<tr class="item-row">
  <td>
    <select name="product_id[]" class="item-product">
      <option value="">Selecione um produto</option>
      <?php foreach ($products as $p): ?><option value="<?= e($p->id) ?>" data-price="<?= e($p->currentPrice?->decimal()) ?>" data-stock="<?= e($p->stock) ?>" <?= $p->id === $productId ? 'selected' : '' ?>><?= e($p->name) ?> (<?= e($p->sku) ?>) — <?= e($fmt->moneyOrDash($p->currentPrice)) ?> · estoque <?= e($p->stock) ?></option><?php endforeach ?>
    </select>
  </td>
  <td><input type="number" name="quantity[]" class="item-qty" min="1" step="1" value="<?= e($quantity) ?>"></td>
  <td><input type="text" name="item_discount[]" class="item-discount" inputmode="decimal" value="<?= e($discount) ?>" placeholder="0,00"></td>
  <td class="num item-total">R$ 0,00</td>
  <td><button type="button" class="btn-link danger remove-row" aria-label="Remover item">Remover</button></td>
</tr>

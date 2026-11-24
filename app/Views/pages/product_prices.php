<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 * @var \Patylook\Core\Formatter $fmt
 */
/** @var \Patylook\Models\Product $product */
$product = $page->data['product'];
/** @var \Patylook\Models\Pricebook $f */
$f = $page->data['form'];
$e = $page->errors;
$view->script('/static/js/product_prices.js');
?>
<div class="section-head">
  <div>
    <h1>Preços</h1>
    <p class="muted"><?= e($product->name) ?> · <code><?= e($product->sku) ?></code></p>
  </div>
  <a class="btn" href="/products">Voltar</a>
</div>

<h2>Nova vigência de preço</h2>
<p class="muted">Informe os custos e o preço final de venda. O markup é calculado automaticamente: (preço final − custo total) ÷ custo total, em que o custo total soma produto, embalagem, logística e outros. A vigência mais recente que cobre a data do pedido é a utilizada.</p>
<form method="post" action="/products/<?= e($product->id) ?>/prices" class="form form-grid" id="price-form">
  <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
  <?= $view->partial('formerror', ['errors' => $e]) ?>
  <?= $view->partial('field', ['label' => 'Custo do produto (R$)', 'name' => 'product_cost', 'value' => $f->productCost->input(), 'errors' => $e, 'required' => true, 'mode' => 'decimal']) ?>
  <?= $view->partial('field', ['label' => 'Custo de embalagem (R$)', 'name' => 'packaging_cost', 'value' => $f->packagingCost->input(), 'errors' => $e, 'mode' => 'decimal']) ?>
  <?= $view->partial('field', ['label' => 'Custo logístico (R$)', 'name' => 'logistics_cost', 'value' => $f->logisticsCost->input(), 'errors' => $e, 'mode' => 'decimal']) ?>
  <?= $view->partial('field', ['label' => 'Outros custos (R$)', 'name' => 'other_costs', 'value' => $f->otherCosts->input(), 'errors' => $e, 'mode' => 'decimal']) ?>
  <?= $view->partial('field', ['label' => 'Preço final (R$)', 'name' => 'final_price', 'value' => $f->finalPrice->input(), 'errors' => $e, 'required' => true, 'mode' => 'decimal', 'hint' => 'Preço de venda. O markup é calculado a partir dele.']) ?>
  <div class="field">
    <label for="f-markup_percent">Markup (%)</label>
    <input id="f-markup_percent" type="text" value="<?= e($f->markupPercent->input()) ?>" readonly>
    <small class="hint">Calculado, não pode ser editado.</small>
  </div>
  <?= $view->partial('field', ['label' => 'Válido a partir de', 'name' => 'valid_from', 'value' => $fmt->dateInput($f->validFrom), 'errors' => $e, 'required' => true, 'type' => 'date']) ?>
  <?= $view->partial('field', ['label' => 'Válido até (opcional)', 'name' => 'valid_to', 'value' => $fmt->dateInput($f->validTo), 'errors' => $e, 'type' => 'date']) ?>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Adicionar preço</button>
  </div>
</form>

<h2>Histórico</h2>
<?php if ($page->data['prices'] !== []): ?>
<div class="table-wrap">
<table>
  <thead><tr><th>Vigência</th><th class="num">Produto</th><th class="num">Embalagem</th><th class="num">Logística</th><th class="num">Outros</th><th class="num">Markup</th><th class="num">Preço final</th><th>Cadastrado por</th></tr></thead>
  <tbody>
  <?php foreach ($page->data['prices'] as $pr): ?>
    <tr>
      <td><?= e($fmt->date($pr->validFrom)) ?> → <?= e($fmt->date($pr->validTo)) ?></td>
      <td class="num"><?= e($fmt->money($pr->productCost)) ?></td>
      <td class="num"><?= e($fmt->money($pr->packagingCost)) ?></td>
      <td class="num"><?= e($fmt->money($pr->logisticsCost)) ?></td>
      <td class="num"><?= e($fmt->money($pr->otherCosts)) ?></td>
      <td class="num"><?= e($fmt->percent($pr->markupPercent)) ?></td>
      <td class="num"><strong><?= e($fmt->money($pr->finalPrice)) ?></strong></td>
      <td><?= e($pr->createdByName) ?> <span class="muted"><?= e($fmt->datetime($pr->createdAt)) ?></span></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
</div>
<?php else: ?>
<p class="empty">Nenhum preço cadastrado. Sem preço vigente o produto não pode ser vendido.</p>
<?php endif ?>

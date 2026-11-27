<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 */
/** @var \Patylook\Http\OrderDraft $form */
$form = $page->data['form'];
$errors = $page->errors;
$products = $page->data['products'];
$view->script('/static/js/order.js');
?>
<h1>Novo pedido</h1>
<form method="post" action="/orders" class="form" id="order-form">
  <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
  <?= $view->partial('formerror', ['errors' => $errors]) ?>

  <div class="form-grid">
    <div class="field<?= isset($errors['customer_id']) ? ' has-error' : '' ?>">
      <label for="f-customer_id">Cliente</label>
      <select id="f-customer_id" name="customer_id" required>
        <option value="">Selecione</option>
        <?php foreach ($page->data['customers'] as $c): ?><option value="<?= e($c->id) ?>" <?= $c->id === $form->customerId ? 'selected' : '' ?>><?= e($c->name) ?> — <?= e($fmt->cpf($c->document)) ?></option><?php endforeach ?>
      </select>
      <?php if (isset($errors['customer_id'])): ?><small class="field-error"><?= e($errors['customer_id']) ?></small><?php endif ?>
    </div>
    <div class="field<?= isset($errors['payment_type_id']) ? ' has-error' : '' ?>">
      <label for="f-payment_type_id">Forma de pagamento</label>
      <select id="f-payment_type_id" name="payment_type_id" required>
        <option value="">Selecione</option>
        <?php foreach ($page->data['paymentTypes'] as $t): ?><option value="<?= e($t->id) ?>" data-surcharge="<?= e($t->surchargePercent->decimal()) ?>" data-category="<?= e($t->category?->value ?? '') ?>" <?= $t->id === $form->paymentTypeId ? 'selected' : '' ?>><?= e($t->name) ?><?= $t->surchargePercent->isZero() ? '' : ' (+' . e($fmt->percent($t->surchargePercent)) . ')' ?></option><?php endforeach ?>
      </select>
      <?php if (isset($errors['payment_type_id'])): ?><small class="field-error"><?= e($errors['payment_type_id']) ?></small><?php endif ?>
    </div>
  </div>

  <?php $credit = false; foreach ($page->data['paymentTypes'] as $t) { $credit = $credit || ($t->id === $form->paymentTypeId && $t->category === \Patylook\Models\PaymentCategory::Credit); } ?>
  <div class="form-grid" id="installments-row"<?= $credit ? '' : ' hidden' ?>>
    <div class="field<?= isset($errors['installments']) ? ' has-error' : '' ?>">
      <label for="f-installments">Parcelas</label>
      <input id="f-installments" name="installments" type="number" inputmode="numeric" min="1" max="<?= e(\Patylook\Models\Order::MAX_INSTALLMENTS) ?>" step="1" value="<?= e($form->installments) ?>" placeholder="Ex.: 3"<?= $credit ? '' : ' disabled' ?>>
      <small class="hint">Pagamento no crédito: quantidade de parcelas (até <?= e(\Patylook\Models\Order::MAX_INSTALLMENTS) ?>).</small>
      <?php if (isset($errors['installments'])): ?><small class="field-error"><?= e($errors['installments']) ?></small><?php endif ?>
    </div>
    <div class="field">
      <label for="t-installment">Valor de cada parcela</label>
      <input id="t-installment" type="text" value="—" readonly tabindex="-1">
      <small class="hint">Só visual: valor final (após desconto e acréscimo) dividido pelas parcelas.</small>
    </div>
  </div>

  <h2>Itens</h2>
  <?php if (isset($errors['items'])): ?><div class="alert alert-error"><?= e($errors['items']) ?></div><?php endif ?>
  <?php if ($products === []): ?><div class="alert alert-error">Nenhum produto disponível: é preciso ter produto ativo, com estoque e preço vigente.</div><?php endif ?>
  <div class="table-wrap">
  <table class="items">
    <thead><tr><th>Produto</th><th>Qtd.</th><th>Desconto (R$)</th><th class="num">Total</th><th></th></tr></thead>
    <tbody id="item-rows">
      <?php foreach ($form->rows as $row): ?><?= $view->partial('order_row', ['products' => $products, 'productId' => $row->productId, 'quantity' => $row->quantity, 'discount' => $row->discount]) ?><?php endforeach ?>
    </tbody>
  </table>
  </div>
  <p><button type="button" class="btn" id="add-row">+ Adicionar item</button></p>

  <div class="form-grid">
    <div class="field<?= isset($errors['discount']) ? ' has-error' : '' ?>">
      <label for="f-discount">Desconto no pedido (R$)</label>
      <input id="f-discount" name="discount" type="text" inputmode="decimal" value="<?= e($form->discount) ?>" placeholder="0,00">
      <small class="hint">Abatido do valor final, depois do acréscimo da forma de pagamento.</small>
      <?php if (isset($errors['discount'])): ?><small class="field-error"><?= e($errors['discount']) ?></small><?php endif ?>
    </div>
    <div class="field">
      <label for="f-notes">Observações</label>
      <textarea id="f-notes" name="notes" rows="3" maxlength="2000"><?= e($form->notes) ?></textarea>
    </div>
  </div>

  <dl class="totals" id="totals">
    <div><dt>Subtotal</dt><dd id="t-subtotal">R$ 0,00</dd></div>
    <div><dt>Acréscimo do pagamento</dt><dd id="t-addition">R$ 0,00</dd></div>
    <div><dt>Desconto no pedido</dt><dd id="t-discount">R$ 0,00</dd></div>
    <div class="grand"><dt>Total</dt><dd id="t-total">R$ 0,00</dd></div>
  </dl>
  <p class="muted">Valores de prévia. Preço, custo e estoque são conferidos e gravados pelo servidor ao salvar.</p>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Criar pedido</button>
    <a class="btn" href="/orders">Cancelar</a>
  </div>
</form>

<template id="row-template">
  <?= $view->partial('order_row', ['products' => $products, 'productId' => 0, 'quantity' => '1', 'discount' => '']) ?>
</template>

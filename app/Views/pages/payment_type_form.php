<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 */
$p = $page->data['paymentType'];
$e = $page->errors;
?>
<h1><?= $p->id ? 'Editar forma de pagamento' : 'Nova forma de pagamento' ?></h1>
<form method="post" action="<?= $p->id ? '/payment-types/' . e($p->id) : '/payment-types' ?>" class="form">
  <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
  <?= $view->partial('formerror', ['errors' => $e]) ?>
  <?= $view->partial('field', ['label' => 'Nome', 'name' => 'name', 'value' => $p->name, 'errors' => $e, 'required' => true, 'max' => 45]) ?>
  <?= $view->partial('field', ['label' => 'Descrição', 'name' => 'description', 'value' => $p->description, 'errors' => $e, 'max' => 45]) ?>
  <div class="field<?= isset($e['category']) ? ' has-error' : '' ?>">
    <label for="f-category">Categoria</label>
    <select id="f-category" name="category" required>
      <?php if ($p->category === null): ?><option value="">Selecione</option><?php endif ?>
      <?php foreach (\Patylook\Models\PaymentCategory::cases() as $c): ?><option value="<?= e($c->value) ?>" <?= $c === $p->category ? 'selected' : '' ?>><?= e($c->label()) ?></option><?php endforeach ?>
    </select>
    <?php if (isset($e['category'])): ?><small class="field-error"><?= e($e['category']) ?></small><?php endif ?>
  </div>
  <?= $view->partial('field', ['label' => 'Acréscimo (%)', 'name' => 'surcharge_percent', 'value' => $p->surchargePercent->input(), 'errors' => $e, 'mode' => 'decimal', 'hint' => 'Aplicado sobre o total do pedido após o desconto']) ?>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a class="btn" href="/payment-types">Cancelar</a>
  </div>
</form>

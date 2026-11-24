<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 * @var \Patylook\Core\Formatter $fmt
 */
$types = $page->data['paymentTypes'];
?>
<div class="section-head">
  <h1>Formas de pagamento</h1>
  <a class="btn btn-primary" href="/payment-types/new">Nova forma de pagamento</a>
</div>
<p class="muted">Apenas classificam o pedido e definem um acréscimo percentual; o sistema não processa pagamentos.</p>
<?php if ($types !== []): ?>
<div class="table-wrap">
<table>
  <thead><tr><th>Nome</th><th>Descrição</th><th class="num">Acréscimo</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($types as $t): ?>
    <tr>
      <td><?= e($t->name) ?></td>
      <td><?= e($t->description) ?></td>
      <td class="num"><?= e($fmt->percent($t->surchargePercent)) ?></td>
      <td class="actions">
        <a href="/payment-types/<?= e($t->id) ?>/edit">Editar</a>
        <form method="post" action="/payment-types/<?= e($t->id) ?>/delete" class="inline" data-confirm="Excluir esta forma de pagamento?">
          <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
          <button type="submit" class="btn-link danger">Excluir</button>
        </form>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
</div>
<?php else: ?>
<p class="empty">Nenhuma forma de pagamento cadastrada. Cadastre ao menos uma para criar pedidos.</p>
<?php endif ?>

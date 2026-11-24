<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 * @var \Patylook\Core\Formatter $fmt
 */
$orders = $page->data['orders'];
$status = $page->data['status'];
?>
<div class="section-head">
  <h1>Pedidos</h1>
  <a class="btn btn-primary" href="/orders/new">Novo pedido</a>
</div>

<form method="get" action="/orders" class="search">
  <input type="search" name="q" value="<?= e($page->data['query']) ?>" placeholder="Buscar por cliente ou nº do pedido">
  <select name="status">
    <option value="">Todos os status</option>
    <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Aberto</option>
    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Concluído</option>
    <option value="canceled" <?= $status === 'canceled' ? 'selected' : '' ?>>Cancelado</option>
  </select>
  <button class="btn" type="submit">Filtrar</button>
</form>

<?php if ($orders !== []): ?>
<div class="table-wrap">
<table>
  <thead><tr><th>#</th><th>Data</th><th>Cliente</th><th>Pagamento</th><th>Atendente</th><th>Status</th><th class="num">Total</th></tr></thead>
  <tbody>
  <?php foreach ($orders as $o): ?>
    <tr>
      <td><a href="/orders/<?= e($o->id) ?>">#<?= e($o->id) ?></a></td>
      <td><?= e($fmt->datetime($o->createdAt)) ?></td>
      <td><?= e($o->customerName) ?></td>
      <td><?= e($o->paymentTypeName) ?></td>
      <td><?= e($o->userName) ?></td>
      <td><?= $view->partial('status_badge', ['status' => $o->status]) ?></td>
      <td class="num"><?= e($fmt->money($o->total)) ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
</div>
<?= $view->partial('pagination', ['pager' => $page->data['pager']]) ?>
<?php else: ?>
<p class="empty">Nenhum pedido encontrado.</p>
<?php endif ?>

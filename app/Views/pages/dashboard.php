<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 * @var \Patylook\Core\Formatter $fmt
 */
/** @var \Patylook\Dto\Dashboard $d */
$d = $page->data['dashboard'];
?>
<h1>Painel</h1>
<p class="muted">Resumo desde <?= e($fmt->date($d->periodStart)) ?></p>

<div class="cards">
  <div class="card"><span class="card-label">Pedidos abertos</span><span class="card-value"><?= e($d->summary->openOrders) ?></span></div>
  <div class="card"><span class="card-label">Pedidos concluídos</span><span class="card-value"><?= e($d->summary->completedOrders) ?></span></div>
  <div class="card"><span class="card-label">Total concluído</span><span class="card-value"><?= e($fmt->money($d->summary->completedTotal)) ?></span></div>
  <div class="card <?= $d->lowStock > 0 ? 'card-warn' : '' ?>"><span class="card-label">Produtos com estoque baixo</span><span class="card-value"><?= e($d->lowStock) ?></span></div>
</div>

<div class="section-head">
  <h2>Pedidos recentes</h2>
  <a class="btn btn-primary" href="/orders/new">Novo pedido</a>
</div>
<?php if ($d->recentOrders !== []): ?>
<div class="table-wrap">
<table>
  <thead><tr><th>#</th><th>Data</th><th>Cliente</th><th>Status</th><th class="num">Total</th></tr></thead>
  <tbody>
  <?php foreach ($d->recentOrders as $o): ?>
    <tr>
      <td><a href="/orders/<?= e($o->id) ?>">#<?= e($o->id) ?></a></td>
      <td><?= e($fmt->datetime($o->createdAt)) ?></td>
      <td><?= e($o->customerName) ?></td>
      <td><?= $view->partial('status_badge', ['status' => $o->status]) ?></td>
      <td class="num"><?= e($fmt->money($o->total)) ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
</div>
<?php else: ?>
<p class="empty">Nenhum pedido ainda.</p>
<?php endif ?>

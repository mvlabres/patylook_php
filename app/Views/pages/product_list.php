<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 * @var \Patylook\Core\Formatter $fmt
 */
$products = $page->data['products'];
?>
<div class="section-head">
  <h1>Produtos</h1>
  <a class="btn btn-primary" href="/products/new">Novo produto</a>
</div>

<form method="get" action="/products" class="search">
  <input type="search" name="q" value="<?= e($page->data['query']) ?>" placeholder="Buscar por nome ou SKU">
  <button class="btn" type="submit">Buscar</button>
</form>

<?php if ($products !== []): ?>
<div class="table-wrap">
<table>
  <thead><tr><th>Nome</th><th>SKU</th><th>Categoria</th><th class="num">Estoque</th><th class="num">Preço atual</th><th class="num">Custos</th><th class="num">Markup</th><th class="num">Ganho real</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($products as $p): ?>
    <tr class="<?= $p->isActive ? '' : 'inactive' ?>">
      <td><?= e($p->name) ?></td>
      <td><code><?= e($p->sku) ?></code></td>
      <td><?= e($p->categoryName) ?></td>
      <td class="num <?= $p->stock <= 5 ? 'warn' : '' ?>"><?= e($p->stock) ?></td>
      <td class="num"><?= e($fmt->moneyOrDash($p->currentPrice)) ?></td>
      <td class="num"><?= e($fmt->moneyOrDash($p->currentCost)) ?></td>
      <td class="num"><?= $p->currentMarkup === null ? '—' : e($fmt->percent($p->currentMarkup)) ?></td>
      <?php $gain = $p->realGain(); ?>
      <td class="num gain<?= $gain !== null && $gain->isNegative() ? ' loss' : '' ?>"><?= e($fmt->moneyOrDash($gain)) ?></td>
      <td><?= $view->partial('active_badge', ['active' => $p->isActive, 'on' => 'Ativo', 'off' => 'Inativo']) ?></td>
      <td class="actions">
        <a href="/products/<?= e($p->id) ?>/edit">Editar</a>
        <a href="/products/<?= e($p->id) ?>/prices">Preços</a>
        <form method="post" action="/products/<?= e($p->id) ?>/active" class="inline" <?= $p->isActive ? 'data-confirm="Desativar este produto?"' : '' ?>>
          <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
          <input type="hidden" name="active" value="<?= $p->isActive ? '0' : '1' ?>">
          <button type="submit" class="btn-link"><?= $p->isActive ? 'Desativar' : 'Ativar' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
  <?php $t = $page->data['totals']; $tm = $t->markup(); ?>
  <tfoot>
    <tr>
      <th colspan="4">Total (<?= e($t->priced) ?> <?= $t->priced === 1 ? 'produto com preço' : 'produtos com preço' ?>, de todas as páginas)</th>
      <td class="num"><?= e($fmt->money($t->price)) ?></td>
      <td class="num"><?= e($fmt->money($t->cost)) ?></td>
      <td class="num"><?= $tm === null ? '—' : e($fmt->percent($tm)) ?></td>
      <td class="num gain<?= $t->gain()->isNegative() ? ' loss' : '' ?>"><?= e($fmt->money($t->gain())) ?></td>
      <td colspan="2"></td>
    </tr>
  </tfoot>
</table>
</div>
<?= $view->partial('pagination', ['pager' => $page->data['pager']]) ?>
<?php else: ?>
<p class="empty">Nenhum produto encontrado.</p>
<?php endif ?>

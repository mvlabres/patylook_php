<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 * @var \Patylook\Core\Formatter $fmt
 */
$brands = $page->data['brands'];
?>
<div class="section-head">
  <h1>Marcas</h1>
  <a class="btn btn-primary" href="/brands/new">Nova marca</a>
</div>

<form method="get" action="/brands" class="search">
  <input type="search" name="q" value="<?= e($page->data['query']) ?>" placeholder="Buscar por nome">
  <button class="btn" type="submit">Buscar</button>
</form>

<?php if ($brands !== []): ?>
<div class="table-wrap">
<table>
  <thead><tr><th>Nome</th><th>Cadastrada em</th><th>Cadastrada por</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($brands as $b): ?>
    <tr class="<?= $b->isActive ? '' : 'inactive' ?>">
      <td><?= e($b->name) ?></td>
      <td><?= e($fmt->datetime($b->createdAt)) ?></td>
      <td><?= e($b->createdByName) ?></td>
      <td><?= $view->partial('active_badge', ['active' => $b->isActive, 'on' => 'Ativa', 'off' => 'Inativa']) ?></td>
      <td class="actions">
        <a href="/brands/<?= e($b->id) ?>/edit">Editar</a>
        <form method="post" action="/brands/<?= e($b->id) ?>/active" class="inline" <?= $b->isActive ? 'data-confirm="Desativar esta marca?"' : '' ?>>
          <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
          <input type="hidden" name="active" value="<?= $b->isActive ? '0' : '1' ?>">
          <button type="submit" class="btn-link"><?= $b->isActive ? 'Desativar' : 'Ativar' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
</div>
<?= $view->partial('pagination', ['pager' => $page->data['pager']]) ?>
<?php else: ?>
<p class="empty">Nenhuma marca encontrada.</p>
<?php endif ?>

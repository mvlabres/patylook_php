<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 * @var \Patylook\Core\Formatter $fmt
 */
$customers = $page->data['customers'];
?>
<div class="section-head">
  <h1>Clientes</h1>
  <a class="btn btn-primary" href="/customers/new">Novo cliente</a>
</div>

<form method="get" action="/customers" class="search">
  <input type="search" name="q" value="<?= e($page->data['query']) ?>" placeholder="Buscar por nome, CPF, e-mail ou telefone">
  <button class="btn" type="submit">Buscar</button>
</form>

<?php if ($customers !== []): ?>
<div class="table-wrap">
<table>
  <thead><tr><th>Nome</th><th>CPF</th><th>Telefone</th><th>E-mail</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($customers as $c): ?>
    <tr class="<?= $c->isActive ? '' : 'inactive' ?>">
      <td><?= e($c->name) ?></td>
      <td><?= e($fmt->cpf($c->document)) ?></td>
      <td><?= e($c->phone) ?></td>
      <td><?= e($c->email) ?></td>
      <td><?= $view->partial('active_badge', ['active' => $c->isActive, 'on' => 'Ativo', 'off' => 'Inativo']) ?></td>
      <td class="actions">
        <a href="/customers/<?= e($c->id) ?>/edit">Editar</a>
        <form method="post" action="/customers/<?= e($c->id) ?>/active" class="inline" <?= $c->isActive ? 'data-confirm="Desativar este cliente?"' : '' ?>>
          <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
          <input type="hidden" name="active" value="<?= $c->isActive ? '0' : '1' ?>">
          <button type="submit" class="btn-link"><?= $c->isActive ? 'Desativar' : 'Ativar' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
</div>
<?= $view->partial('pagination', ['pager' => $page->data['pager']]) ?>
<?php else: ?>
<p class="empty">Nenhum cliente encontrado.</p>
<?php endif ?>

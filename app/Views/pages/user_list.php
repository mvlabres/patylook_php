<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 */
$users = $page->data['users'];
?>
<div class="section-head">
  <h1>Usuários</h1>
  <a class="btn btn-primary" href="/users/new">Novo usuário</a>
</div>

<form method="get" action="/users" class="search">
  <input type="search" name="q" value="<?= e($page->data['query']) ?>" placeholder="Buscar por nome, usuário ou e-mail">
  <button class="btn" type="submit">Buscar</button>
</form>

<?php if ($users !== []): ?>
<div class="table-wrap">
<table>
  <thead><tr><th>Nome</th><th>Usuário</th><th>E-mail</th><th>Categoria</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr class="<?= $u->isActive ? '' : 'inactive' ?>">
      <td><?= e($u->name) ?></td>
      <td><code><?= e($u->username) ?></code></td>
      <td><?= e($u->email) ?></td>
      <td><?= e($u->categoryName) ?></td>
      <td><?= $view->partial('active_badge', ['active' => $u->isActive, 'on' => 'Ativo', 'off' => 'Inativo']) ?></td>
      <td class="actions">
        <a href="/users/<?= e($u->id) ?>/edit">Editar</a>
        <?php if ($u->id !== $page->user->id): ?>
        <form method="post" action="/users/<?= e($u->id) ?>/active" class="inline" <?= $u->isActive ? 'data-confirm="Desativar este usuário? Ele será desconectado."' : '' ?>>
          <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
          <input type="hidden" name="active" value="<?= $u->isActive ? '0' : '1' ?>">
          <button type="submit" class="btn-link"><?= $u->isActive ? 'Desativar' : 'Ativar' ?></button>
        </form>
        <?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
</div>
<?= $view->partial('pagination', ['pager' => $page->data['pager']]) ?>
<?php else: ?>
<p class="empty">Nenhum usuário encontrado.</p>
<?php endif ?>

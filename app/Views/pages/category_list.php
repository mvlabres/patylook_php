<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 */
$categories = $page->data['categories'];
?>
<div class="section-head">
  <h1>Categorias de produtos</h1>
  <a class="btn btn-primary" href="/categories/new">Nova categoria</a>
</div>
<?php if ($categories !== []): ?>
<div class="table-wrap">
<table>
  <thead><tr><th>Nome</th><th>Descrição</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($categories as $c): ?>
    <tr>
      <td><?= e($c->name) ?></td>
      <td><?= e($c->description) ?></td>
      <td class="actions">
        <a href="/categories/<?= e($c->id) ?>/edit">Editar</a>
        <form method="post" action="/categories/<?= e($c->id) ?>/delete" class="inline" data-confirm="Excluir esta categoria?">
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
<p class="empty">Nenhuma categoria cadastrada. Cadastre ao menos uma para criar produtos.</p>
<?php endif ?>

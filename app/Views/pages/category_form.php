<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 */
$c = $page->data['category'];
?>
<h1><?= $c->id ? 'Editar categoria' : 'Nova categoria' ?></h1>
<form method="post" action="<?= $c->id ? '/categories/' . e($c->id) : '/categories' ?>" class="form">
  <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
  <?= $view->partial('formerror', ['errors' => $page->errors]) ?>
  <?= $view->partial('field', ['label' => 'Nome', 'name' => 'name', 'value' => $c->name, 'errors' => $page->errors, 'required' => true, 'max' => 45]) ?>
  <?= $view->partial('field', ['label' => 'Descrição', 'name' => 'description', 'value' => $c->description, 'errors' => $page->errors, 'max' => 45]) ?>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a class="btn" href="/categories">Cancelar</a>
  </div>
</form>

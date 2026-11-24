<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 */
$b = $page->data['brand'];
?>
<h1><?= $b->id ? 'Editar marca' : 'Nova marca' ?></h1>
<form method="post" action="<?= $b->id ? '/brands/' . e($b->id) : '/brands' ?>" class="form">
  <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
  <?= $view->partial('formerror', ['errors' => $page->errors]) ?>
  <?= $view->partial('field', ['label' => 'Nome', 'name' => 'name', 'value' => $b->name, 'errors' => $page->errors, 'required' => true, 'max' => 100]) ?>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a class="btn" href="/brands">Cancelar</a>
  </div>
</form>

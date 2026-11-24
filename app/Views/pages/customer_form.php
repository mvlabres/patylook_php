<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 */
$c = $page->data['customer'];
$e = $page->errors;
?>
<h1><?= $c->id ? 'Editar cliente' : 'Novo cliente' ?></h1>
<form method="post" action="<?= $c->id ? '/customers/' . e($c->id) : '/customers' ?>" class="form">
  <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
  <?= $view->partial('formerror', ['errors' => $e]) ?>
  <?= $view->partial('field', ['label' => 'Nome', 'name' => 'name', 'value' => $c->name, 'errors' => $e, 'required' => true, 'max' => 100]) ?>
  <?= $view->partial('field', ['label' => 'CPF', 'name' => 'document', 'value' => $c->document, 'errors' => $e, 'required' => true, 'max' => 14, 'mode' => 'numeric', 'hint' => 'Apenas números ou com pontuação']) ?>
  <?= $view->partial('field', ['label' => 'Telefone', 'name' => 'phone', 'value' => $c->phone, 'errors' => $e, 'required' => true, 'max' => 20, 'type' => 'tel']) ?>
  <?= $view->partial('field', ['label' => 'E-mail', 'name' => 'email', 'value' => $c->email, 'errors' => $e, 'required' => true, 'max' => 45, 'type' => 'email']) ?>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a class="btn" href="/customers">Cancelar</a>
  </div>
</form>

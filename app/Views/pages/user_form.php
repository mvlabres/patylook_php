<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 */
/** @var \Patylook\Models\User $u */
$u = $page->data['edited'];
$errors = $page->errors;
?>
<h1><?= $u->id ? 'Editar usuário' : 'Novo usuário' ?></h1>
<form method="post" action="<?= $u->id ? '/users/' . e($u->id) : '/users' ?>" class="form">
  <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
  <?= $view->partial('formerror', ['errors' => $errors]) ?>
  <?= $view->partial('field', ['label' => 'Nome', 'name' => 'name', 'value' => $u->name, 'errors' => $errors, 'required' => true, 'max' => 100]) ?>
  <?= $view->partial('field', ['label' => 'Usuário (login)', 'name' => 'username', 'value' => $u->username, 'errors' => $errors, 'required' => true, 'max' => 16, 'hint' => '3 a 16 caracteres: letras, números, ponto, hífen ou sublinhado']) ?>
  <?= $view->partial('field', ['label' => 'E-mail', 'name' => 'email', 'value' => $u->email, 'errors' => $errors, 'max' => 255, 'type' => 'email']) ?>
  <div class="field<?= isset($errors['user_category_id']) ? ' has-error' : '' ?>">
    <label for="f-user_category_id">Categoria</label>
    <select id="f-user_category_id" name="user_category_id" required>
      <option value="">Selecione</option>
      <?php foreach ($page->data['categories'] as $c): ?><option value="<?= e($c->id) ?>" <?= $c->id === $u->categoryId ? 'selected' : '' ?>><?= e($c->name) ?></option><?php endforeach ?>
    </select>
    <?php if (isset($errors['user_category_id'])): ?><small class="field-error"><?= e($errors['user_category_id']) ?></small><?php endif ?>
  </div>
  <?php if (!$u->id): ?>
    <div class="form-grid">
      <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
        <label for="f-password">Senha</label>
        <input id="f-password" name="password" type="password" required autocomplete="new-password">
        <?php if (isset($errors['password'])): ?><small class="field-error"><?= e($errors['password']) ?></small><?php endif ?>
      </div>
      <div class="field<?= isset($errors['confirm_password']) ? ' has-error' : '' ?>">
        <label for="f-confirm_password">Confirmar senha</label>
        <input id="f-confirm_password" name="confirm_password" type="password" required autocomplete="new-password">
        <?php if (isset($errors['confirm_password'])): ?><small class="field-error"><?= e($errors['confirm_password']) ?></small><?php endif ?>
      </div>
    </div>
  <?php endif ?>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a class="btn" href="/users">Cancelar</a>
  </div>
</form>

<?php if ($u->id): ?>
<h2>Redefinir senha</h2>
<form method="post" action="/users/<?= e($u->id) ?>/password" class="form" data-confirm="Redefinir a senha deste usuário? Ele será desconectado.">
  <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
  <div class="form-grid">
    <div class="field">
      <label for="f-new-password">Nova senha</label>
      <input id="f-new-password" name="password" type="password" required autocomplete="new-password">
    </div>
    <div class="field">
      <label for="f-new-confirm">Confirmar nova senha</label>
      <input id="f-new-confirm" name="confirm_password" type="password" required autocomplete="new-password">
    </div>
  </div>
  <div class="form-actions"><button type="submit" class="btn">Redefinir senha</button></div>
</form>
<?php endif ?>

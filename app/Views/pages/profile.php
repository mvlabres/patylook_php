<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 */
$user = $page->user;
$errors = $page->errors;
?>
<h1>Meu perfil</h1>
<dl class="details">
  <div><dt>Nome</dt><dd><?= e($user->name) ?></dd></div>
  <div><dt>Usuário</dt><dd><code><?= e($user->username) ?></code></dd></div>
  <div><dt>Categoria</dt><dd><?= e($user->categoryName) ?></dd></div>
</dl>

<h2>Alterar senha</h2>
<p class="muted">Ao alterar a senha você será desconectado de todos os dispositivos.</p>
<form method="post" action="/profile/password" class="form">
  <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
  <?= $view->partial('formerror', ['errors' => $errors]) ?>
  <div class="field<?= isset($errors['current_password']) ? ' has-error' : '' ?>">
    <label for="f-current_password">Senha atual</label>
    <input id="f-current_password" name="current_password" type="password" required autocomplete="current-password">
    <?php if (isset($errors['current_password'])): ?><small class="field-error"><?= e($errors['current_password']) ?></small><?php endif ?>
  </div>
  <div class="form-grid">
    <div class="field<?= isset($errors['new_password']) ? ' has-error' : '' ?>">
      <label for="f-new_password">Nova senha</label>
      <input id="f-new_password" name="new_password" type="password" required autocomplete="new-password">
      <?php if (isset($errors['new_password'])): ?><small class="field-error"><?= e($errors['new_password']) ?></small><?php endif ?>
    </div>
    <div class="field<?= isset($errors['confirm_password']) ? ' has-error' : '' ?>">
      <label for="f-confirm_password">Confirmar nova senha</label>
      <input id="f-confirm_password" name="confirm_password" type="password" required autocomplete="new-password">
      <?php if (isset($errors['confirm_password'])): ?><small class="field-error"><?= e($errors['confirm_password']) ?></small><?php endif ?>
    </div>
  </div>
  <div class="form-actions"><button type="submit" class="btn btn-primary">Alterar senha</button></div>
</form>

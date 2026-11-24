<?php /** @var \Patylook\Core\PageData $page */ /** @var \Patylook\Core\View $view */ ?>
<div class="login-card">
  <h1>Patylook</h1>
  <p class="muted">Gestão de pedidos</p>
  <?= $view->partial('formerror', ['errors' => $page->errors]) ?>
  <form method="post" action="/login">
    <div class="field">
      <label for="f-username">Usuário</label>
      <input id="f-username" name="username" type="text" value="<?= e($page->data['username']) ?>" required autofocus autocomplete="username" maxlength="16">
    </div>
    <div class="field">
      <label for="f-password">Senha</label>
      <input id="f-password" name="password" type="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Entrar</button>
  </form>
</div>

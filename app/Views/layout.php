<?php
/** @var \Patylook\Core\PageData $page */
/** @var \Patylook\Core\View $view */
/** @var string $content */
$user = $page->user;
$nav = static fn (string $key): string => $page->active === $key ? 'active' : '';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page->title) ?> · Patylook</title>
  <link rel="stylesheet" href="/static/css/app.css">
</head>
<body>
<?php if ($user !== null): ?>
<header class="topbar">
  <a class="brand" href="/">Patylook</a>
  <nav class="nav">
    <a href="/" class="<?= $nav('dashboard') ?>">Painel</a>
    <a href="/orders" class="<?= $nav('orders') ?>">Pedidos</a>
    <a href="/products" class="<?= $nav('products') ?>">Produtos</a>
    <a href="/showcase" class="<?= $nav('showcase') ?>">Mostruário</a>
    <a href="/categories" class="<?= $nav('categories') ?>">Categorias</a>
    <a href="/brands" class="<?= $nav('brands') ?>">Marcas</a>
    <a href="/customers" class="<?= $nav('customers') ?>">Clientes</a>
    <a href="/payment-types" class="<?= $nav('payment-types') ?>">Pagamentos</a>
    <?php if ($user->admin): ?><a href="/users" class="<?= $nav('users') ?>">Usuários</a><?php endif ?>
  </nav>
  <div class="user-menu">
    <a href="/profile" class="<?= $nav('profile') ?>"><?= e($user->name) ?></a>
    <form method="post" action="/logout" class="inline">
      <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
      <button type="submit" class="btn btn-small">Sair</button>
    </form>
  </div>
</header>
<?php endif ?>
<main class="container<?= $user === null ? ' auth' : '' ?>">
  <?php if ($page->flash !== null): ?><div class="alert alert-<?= e($page->flash->kind->value) ?>" role="status"><?= e($page->flash->message) ?></div><?php endif ?>
  <?= $content ?>
</main>
<script src="/static/js/app.js"></script>
<?php foreach ($view->scripts() as $src): ?><script src="<?= e($src) ?>"></script><?php endforeach ?>

</body>
</html>

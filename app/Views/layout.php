<?php
/** @var \Patylook\Core\PageData $page */
/** @var \Patylook\Core\View $view */
/** @var string $content */
$user = $page->user;
$nav = static fn (string $key): string => $page->active === $key ? 'active' : '';

// Line icons (24x24 grid, stroke only) shown next to each menu entry.
$icons = [
    'dashboard' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
    'orders' => '<circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M2 3h3l2.7 12.4a1 1 0 0 0 1 .8h9.6a1 1 0 0 0 1-.8L21 7H6"/>',
    'products' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
    'showcase' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'categories' => '<path d="m12 2 10 5-10 5L2 7z"/><path d="m2 12 10 5 10-5"/><path d="m2 17 10 5 10-5"/>',
    'brands' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1"/>',
    'customers' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
    'payment-types' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    'users' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
];
$icon = static fn (string $key): string => '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true">' . $icons[$key] . '</svg>';

$menu = [
    ['/', 'dashboard', 'Painel'],
    ['/orders', 'orders', 'Pedidos'],
    ['/products', 'products', 'Produtos'],
    ['/showcase', 'showcase', 'Mostruário'],
    ['/categories', 'categories', 'Categorias'],
    ['/brands', 'brands', 'Marcas'],
    ['/customers', 'customers', 'Clientes'],
    ['/payment-types', 'payment-types', 'Pagamentos'],
];
if ($user !== null && $user->admin) {
    $menu[] = ['/users', 'users', 'Usuários'];
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b0a09">
  <title><?= e($page->title) ?> · VL Store</title>
  <link rel="icon" type="image/png" sizes="32x32" href="/static/img/favicon-32.png">
  <link rel="apple-touch-icon" href="/static/img/apple-touch-icon.png">
  <link rel="preload" href="/static/fonts/montserrat-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="/static/css/app.css">
</head>
<body>
<?php if ($user !== null): ?>
<header class="topbar">
  <button type="button" class="menu-toggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="side-menu" data-menu-toggle>
    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
  </button>
  <a class="brand" href="/" aria-label="VL Store · Valentina Labres"><img src="/static/img/logo.png" width="720" height="355" alt="VL Store"></a>
  <div class="topbar-bar" id="side-menu">
    <button type="button" class="menu-close" aria-label="Fechar menu" data-menu-close>&times;</button>
    <nav class="nav">
      <?php foreach ($menu as [$href, $key, $label]): ?>
      <a href="<?= e($href) ?>" class="<?= $nav($key) ?>"><?= $icon($key) ?><span><?= e($label) ?></span></a>
      <?php endforeach ?>
    </nav>
    <div class="user-menu">
      <a href="/profile" class="<?= $nav('profile') ?>"><?= e($user->name) ?></a>
      <form method="post" action="/logout" class="inline">
        <?= $view->partial('csrf', ['csrf' => $page->csrf]) ?>
        <button type="submit" class="btn btn-small btn-outline"><?= $icon('logout') ?>Sair</button>
      </form>
    </div>
  </div>
  <div class="menu-backdrop" data-menu-close></div>
</header>
<?php elseif (!empty($page->data['public'])): ?>
<header class="topbar topbar-public">
  <span class="brand"><img src="/static/img/logo.png" width="720" height="355" alt="VL Store · Valentina Labres"></span>
</header>
<?php endif ?>
<main class="container<?= $user === null && empty($page->data['public']) ? ' auth' : '' ?>">
  <?php if ($page->flash !== null): ?><div class="alert alert-<?= e($page->flash->kind->value) ?>" role="status"><?= e($page->flash->message) ?></div><?php endif ?>
  <?= $content ?>
</main>
<footer class="payment-footer">
  <div class="payment-container">
    <p class="payment-title">Aceitamos os seguintes cartões de crédito:</p>
    <div class="payment-cards">
      <span class="card-badge card-visa">VISA</span>
      <span class="card-badge card-mastercard">Mastercard</span>
      <span class="card-badge card-elo">Elo</span>
      <span class="card-badge card-hipercard">Hipercard</span>
      <span class="card-badge card-amex">American Express</span>
    </div>
  </div>
</footer>
<script src="/static/js/app.js"></script>
<?php foreach ($view->scripts() as $src): ?><script src="<?= e($src) ?>"></script><?php endforeach ?>

</body>
</html>

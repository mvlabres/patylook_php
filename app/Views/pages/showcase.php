<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 * @var \Patylook\Core\Formatter $fmt
 */
$items = $page->data['items'];
$view->script('/static/js/showcase.js');
?>
<div class="section-head">
  <h1>Mostruário</h1>
  <a class="btn" href="/products">Gerenciar produtos</a>
</div>

<form method="get" action="/showcase" class="search">
  <input type="search" name="q" value="<?= e($page->data['query']) ?>" placeholder="Buscar por nome ou SKU">
  <button class="btn" type="submit">Buscar</button>
</form>

<?php if ($items !== []): ?>
<div class="showcase-grid">
  <?php foreach ($items as $item): ?>
  <?php $name = $item->product->name; $main = $item->main(); ?>
  <article class="product-card" data-name="<?= e($name) ?>">
    <?php if ($main !== null): ?>
    <button type="button" class="banner" data-gallery-open aria-label="Ver imagens de <?= e($name) ?>">
      <img src="<?= e($main->url()) ?>" alt="<?= e($name) ?>" loading="lazy">
    </button>
    <?php else: ?>
    <div class="banner banner-empty"><span>Sem imagem</span></div>
    <?php endif ?>
    <div class="card-body">
      <h2><?= e($name) ?></h2>
      <p class="muted"><?= e($item->product->categoryName) ?> · <code><?= e($item->product->sku) ?></code></p>
      <p class="price"><?= e($fmt->moneyOrDash($item->product->currentPrice)) ?></p>
      <p class="stock <?= $item->product->stock <= 5 ? 'warn' : '' ?>"><?= $item->product->stock > 0 ? e($item->product->stock) . ' em estoque' : 'Esgotado' ?></p>
      <?php if ($item->product->description !== ''): ?><p class="desc"><?= e($item->product->description) ?></p><?php endif ?>
    </div>
    <ul class="gallery-data" hidden>
      <?php foreach ($item->images as $img): ?><li data-src="<?= e($img->url()) ?>"></li><?php endforeach ?>
    </ul>
  </article>
  <?php endforeach ?>
</div>
<?= $view->partial('pagination', ['pager' => $page->data['pager']]) ?>

<dialog id="gallery" class="gallery" aria-label="Imagens do produto">
  <div class="gallery-inner">
    <button type="button" class="gallery-close" id="gallery-close" aria-label="Fechar">×</button>
    <p class="gallery-title" id="gallery-title"></p>
    <div class="gallery-stage">
      <button type="button" class="gallery-nav" id="gallery-prev" aria-label="Imagem anterior">‹</button>
      <img id="gallery-image" alt="">
      <button type="button" class="gallery-nav" id="gallery-next" aria-label="Próxima imagem">›</button>
    </div>
    <div class="gallery-thumbs" id="gallery-thumbs"></div>
  </div>
</dialog>
<?php else: ?>
<p class="empty">Nenhum produto ativo para exibir.</p>
<?php endif ?>

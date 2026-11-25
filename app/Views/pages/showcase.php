<?php
/**
 * @var \Patylook\Core\PageData $page
 * @var \Patylook\Core\View $view
 * @var \Patylook\Core\Formatter $fmt
 */
$items = $page->data['items'];
$view->script('/static/js/showcase.js');
// The public view (/mostruario) has no management actions and reads images from its own route.
$public = (bool) ($page->data['public'] ?? false);
/** @var ?\Patylook\Models\MainSeller $seller */
$seller = $page->data['seller'] ?? null;
$imageUrl = static fn (\Patylook\Models\ProductImage $i): string => $public ? $i->publicUrl() : $i->url();
?>
<div class="section-head">
  <h1>Mostruário</h1>
  <?php if (!$public): ?><a class="btn" href="/products">Gerenciar produtos</a><?php endif ?>
</div>

<form method="get" action="<?= $public ? '/mostruario' : '/showcase' ?>" class="search" data-filter-form>
  <select name="gender" aria-label="Filtrar por gênero" autocomplete="off">
    <option value="">Todos</option>
    <?php foreach (\Patylook\Models\Gender::cases() as $g): ?><option value="<?= e($g->value) ?>" <?= $g === ($page->data['gender'] ?? null) ? 'selected' : '' ?>><?= e($g->label()) ?></option><?php endforeach ?>
  </select>
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
      <img src="<?= e($imageUrl($main)) ?>" alt="<?= e($name) ?>" loading="lazy">
    </button>
    <?php else: ?>
    <div class="banner banner-empty"><span>Sem imagem</span></div>
    <?php endif ?>
    <div class="card-body">
      <h2><?= e($name) ?></h2>
      <p class="chips">
        <span class="chip"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1"/></svg><?= e($item->product->categoryName) ?></span>
        <span class="chip chip-sku"><span class="chip-label">SKU:</span> <?= e($item->product->sku) ?></span>
      </p>
      <p class="price"><?= e($fmt->moneyOrDash($item->product->currentPrice)) ?></p>
      <p class="stock <?= $item->product->stock <= 5 ? 'warn' : '' ?>"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg><?= $item->product->stock > 0 ? e($item->product->stock) . ' em estoque' : 'Esgotado' ?></p>
      <?php $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $item->product->description) ?: []), static fn (string $l): bool => $l !== '')); ?>
      <?php if ($lines !== []): ?>
      <ul class="desc"><?php foreach ($lines as $line): ?><li><?= e($line) ?></li><?php endforeach ?></ul>
      <?php endif ?>
      <?php $chat = $public ? $seller?->chatUrl(\Patylook\Models\MainSeller::productMessage($name, $item->product->sku)) : null; ?>
      <?php if ($chat !== null): ?>
      <a class="btn btn-whatsapp" href="<?= e($chat) ?>" target="_blank" rel="noopener noreferrer" aria-label="Falar com o vendedor no WhatsApp sobre <?= e($name) ?>">
        <svg class="icon-fill" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
        Falar com o vendedor
      </a>
      <?php endif ?>
    </div>
    <ul class="gallery-data" hidden>
      <?php foreach ($item->images as $img): ?><li data-src="<?= e($imageUrl($img)) ?>"></li><?php endforeach ?>
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
